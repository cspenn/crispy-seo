<?php
/**
 * Media Replacer
 *
 * Handles replacing media files without delete/re-upload.
 * Updates all database references including serialized data.
 *
 * @package CrispySEO\Media
 * @since 2.0.0
 */

declare(strict_types=1);

namespace CrispySEO\Media;

use CrispySEO\Tools\SerializedHandler;

/**
 * Replaces media files and updates references.
 */
class MediaReplacer {

	/**
	 * Serialized handler instance.
	 *
	 * @var SerializedHandler
	 */
	private SerializedHandler $serializedHandler;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->serializedHandler = new SerializedHandler();
		$this->initHooks();
	}

	/**
	 * Initialize WordPress hooks.
	 */
	private function initHooks(): void {
		if ( \is_admin() ) {
			// Add replace button to attachment edit screen.
			\add_filter( 'attachment_fields_to_edit', [ $this, 'addReplaceField' ], 10, 2 );

			// Handle file replacement.
			\add_action( 'wp_ajax_crispy_seo_replace_media', [ $this, 'ajaxReplaceMedia' ] );
			\add_action( 'wp_ajax_crispy_seo_restore_media', [ $this, 'ajaxRestoreMedia' ] );

			// Add metabox to attachment edit.
			\add_action( 'add_meta_boxes_attachment', [ $this, 'addMetaBox' ] );
		}
	}

	/**
	 * Add meta box to attachment edit screen.
	 *
	 * @param \WP_Post $post Post object.
	 */
	public function addMetaBox( \WP_Post $post ): void {
		add_meta_box(
			'crispy-seo-media-replace',
			\__( 'Replace Media', 'crispy-seo' ),
			[ $this, 'renderMetaBox' ],
			'attachment',
			'side',
			'high'
		);
	}

	/**
	 * Render media replace meta box.
	 *
	 * @param \WP_Post $post Post object.
	 */
	public function renderMetaBox( \WP_Post $post ): void {
		include CRISPY_SEO_DIR . 'views/media-replace-metabox.php';
	}

	/**
	 * Add replace field to attachment edit form.
	 *
	 * @param array    $formFields Form fields.
	 * @param \WP_Post $post       Post object.
	 * @return array Modified fields.
	 */
	public function addReplaceField( array $formFields, \WP_Post $post ): array {
		// Only for images and documents.
		$mimeType = get_post_mime_type( $post->ID );

		if ( strpos( $mimeType, 'image/' ) !== 0 && strpos( $mimeType, 'application/' ) !== 0 ) {
			return $formFields;
		}

		$hasBackup = $this->hasBackup( $post->ID );

		$html = '<div class="crispy-media-replace-field">';
		$html .= \wp_nonce_field( 'crispy_seo_media_replace', 'crispy_media_replace_nonce', true, false );
		$html .= '<input type="file" name="crispy_replacement_file" id="crispy-replacement-file" />';
		$html .= '<p class="description">' . esc_html\__( 'Select a new file to replace the current one.', 'crispy-seo' ) . '</p>';

		if ( $hasBackup ) {
			$html .= '<p><a href="#" class="button crispy-restore-backup" data-id="' . \absint( $post->ID ) . '">';
			$html .= esc_html\__( 'Restore Original', 'crispy-seo' ) . '</a></p>';
		}

		$html .= '</div>';

		$formFields['crispy_replace'] = [
			'label' => \__( 'Replace File', 'crispy-seo' ),
			'input' => 'html',
			'html'  => $html,
		];

		return $formFields;
	}

	/**
	 * Replace a media file.
	 *
	 * @param int    $attachmentId Attachment ID.
	 * @param string $newFilePath  Path to new file.
	 * @param bool   $backup       Whether to backup original.
	 * @return array{success: bool, message: string, references_updated: int} Result.
	 */
	public function replaceFile( int $attachmentId, string $newFilePath, bool $backup = true ): array {
		// Validate attachment.
		$attachment = \get_post( $attachmentId );

		if ( ! $attachment || $attachment->post_type !== 'attachment' ) {
			return [
				'success'             => false,
				'message'             => \__( 'Invalid attachment.', 'crispy-seo' ),
				'references_updated' => 0,
			];
		}

		// Validate new file.
		if ( ! file_exists( $newFilePath ) ) {
			return [
				'success'             => false,
				'message'             => \__( 'Replacement file not found.', 'crispy-seo' ),
				'references_updated' => 0,
			];
		}

		$currentFile = \get_attached_file( $attachmentId );

		if ( ! $currentFile ) {
			return [
				'success'             => false,
				'message'             => \__( 'Current file not found.', 'crispy-seo' ),
				'references_updated' => 0,
			];
		}

		// Validate MIME type matches or is compatible.
		$currentMime = get_post_mime_type( $attachmentId );
		$newMime     = mime_content_type( $newFilePath );

		// mime_content_type can return false if file is unreadable or extension unavailable.
		if ( $newMime === false ) {
			return [
				'success'             => false,
				'message'             => \__( 'Unable to determine MIME type of replacement file.', 'crispy-seo' ),
				'references_updated' => 0,
			];
		}

		if ( ! $this->areMimesCompatible( $currentMime, $newMime ) ) {
			return [
				'success'             => false,
				'message'             => sprintf(
					/* translators: 1: current MIME type, 2: new MIME type */
					\__( 'MIME type mismatch: %1$s vs %2$s', 'crispy-seo' ),
					$currentMime,
					$newMime
				),
				'references_updated' => 0,
			];
		}

		// Create backup if enabled.
		if ( $backup ) {
			$this->createBackup( $attachmentId, $currentFile );
		}

		$oldUrl = \wp_get_attachment_url( $attachmentId );

		// Replace the file.
		if ( ! copy( $newFilePath, $currentFile ) ) {
			return [
				'success'             => false,
				'message'             => \__( 'Failed to copy replacement file.', 'crispy-seo' ),
				'references_updated' => 0,
			];
		}

		// Regenerate thumbnails for images.
		if ( strpos( $currentMime, 'image/' ) === 0 ) {
			$this->regenerateThumbnails( $attachmentId );
		}

		// Update MIME type if changed.
		if ( $currentMime !== $newMime ) {
			\wp_update_post(
				[
					'ID'             => $attachmentId,
					'post_mime_type' => $newMime,
				]
			);
		}

		// Update references if URL structure changed.
		$newUrl            = \wp_get_attachment_url( $attachmentId );
		$referencesUpdated = 0;

		if ( $oldUrl !== $newUrl ) {
			$referencesUpdated = $this->updateReferences( $attachmentId, $oldUrl, $newUrl );
		}

		// Clear optimization metadata.
		\delete_post_meta( $attachmentId, '_crispy_seo_optimized_at' );
		\delete_post_meta( $attachmentId, '_crispy_seo_original_size' );
		\delete_post_meta( $attachmentId, '_crispy_seo_optimized_size' );

		// Record replacement.
		\update_post_meta( $attachmentId, '_crispy_seo_replaced_at', current_time( 'mysql' ) );

		return [
			'success'             => true,
			'message'             => \__( 'File replaced successfully.', 'crispy-seo' ),
			'references_updated' => $referencesUpdated,
		];
	}

	/**
	 * Check if MIME types are compatible.
	 *
	 * @param string $current Current MIME type.
	 * @param string $new     New MIME type.
	 * @return bool True if compatible.
	 */
	private function areMimesCompatible( string $current, string $new ): bool {
		// Same type is always compatible.
		if ( $current === $new ) {
			return true;
		}

		// Allow image-to-image replacements.
		$currentType = explode( '/', $current )[0] ?? '';
		$newType     = explode( '/', $new )[0] ?? '';

		if ( $currentType === 'image' && $newType === 'image' ) {
			return true;
		}

		// Allow document-to-document replacements.
		if ( $currentType === 'application' && $newType === 'application' ) {
			return true;
		}

		return false;
	}

	/**
	 * Regenerate thumbnails for an image.
	 *
	 * @param int $attachmentId Attachment ID.
	 * @return bool Success.
	 */
	private function regenerateThumbnails( int $attachmentId ): bool {
		$filePath = \get_attached_file( $attachmentId );

		if ( ! $filePath ) {
			return false;
		}

		// Delete old thumbnails.
		$metadata = \wp_get_attachment_metadata( $attachmentId );

		if ( is_array( $metadata ) && ! empty( $metadata['sizes'] ) ) {
			$uploadDir = \wp_upload_dir();
			$baseDir   = dirname( $filePath );

			foreach ( $metadata['sizes'] as $size ) {
				$thumbPath = $baseDir . '/' . $size['file'];
				if ( file_exists( $thumbPath ) ) {
					wp_delete_file( $thumbPath );
				}
			}
		}

		// Generate new metadata and thumbnails.
		$newMetadata = \wp_generate_attachment_metadata( $attachmentId, $filePath );

		if ( $newMetadata ) {
			\wp_update_attachment_metadata( $attachmentId, $newMetadata );
			return true;
		}

		return false;
	}

	/**
	 * Update all references to an attachment URL.
	 *
	 * @param int    $attachmentId Attachment ID.
	 * @param string $oldUrl       Old URL.
	 * @param string $newUrl       New URL.
	 * @return int Number of references updated.
	 */
	public function updateReferences( int $attachmentId, string $oldUrl, string $newUrl ): int {
		global $wpdb;

		$updated = 0;

		// Update post content.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$postsUpdated = $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$wpdb->posts} SET post_content = REPLACE(post_content, %s, %s) WHERE post_content LIKE %s",
				$oldUrl,
				$newUrl,
				'%' . $wpdb->esc_like( $oldUrl ) . '%'
			)
		);
		$updated     += (int) $postsUpdated;

		// Update post excerpts.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$excerptsUpdated = $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$wpdb->posts} SET post_excerpt = REPLACE(post_excerpt, %s, %s) WHERE post_excerpt LIKE %s",
				$oldUrl,
				$newUrl,
				'%' . $wpdb->esc_like( $oldUrl ) . '%'
			)
		);
		$updated        += (int) $excerptsUpdated;

		// Update post meta (handling serialized data).
		$updated += $this->updateMetaReferences( $wpdb->postmeta, $oldUrl, $newUrl );

		// Update options (handling serialized data).
		$updated += $this->updateOptionsReferences( $oldUrl, $newUrl );

		// Update term meta.
		$updated += $this->updateMetaReferences( $wpdb->termmeta, $oldUrl, $newUrl );

		// Update user meta.
		$updated += $this->updateMetaReferences( $wpdb->usermeta, $oldUrl, $newUrl );

		// Update comment meta.
		$updated += $this->updateMetaReferences( $wpdb->commentmeta, $oldUrl, $newUrl );

		return $updated;
	}

	/**
	 * Update meta table references.
	 *
	 * @param string $table  Table name.
	 * @param string $oldUrl Old URL.
	 * @param string $newUrl New URL.
	 * @return int Number of rows updated.
	 */
	private function updateMetaReferences( string $table, string $oldUrl, string $newUrl ): int {
		global $wpdb;

		$updated = 0;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT meta_id, meta_value FROM {$table} WHERE meta_value LIKE %s",
				'%' . $wpdb->esc_like( $oldUrl ) . '%'
			),
			ARRAY_A
		);

		if ( empty( $rows ) ) {
			return 0;
		}

		foreach ( $rows as $row ) {
			$metaId    = (int) $row['meta_id'];
			$metaValue = $row['meta_value'];

			// Check if serialized.
			if ( $this->serializedHandler->isSerialized( $metaValue ) ) {
				$newValue = $this->serializedHandler->replaceInSerialized( $metaValue, $oldUrl, $newUrl );

				if ( $newValue === false ) {
					continue; // Skip if replacement failed.
				}
			} else {
				$newValue = str_replace( $oldUrl, $newUrl, $metaValue );
			}

			if ( $newValue !== $metaValue ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$result = $wpdb->update(
					$table,
					[ 'meta_value' => $newValue ],
					[ 'meta_id' => $metaId ],
					[ '%s' ],
					[ '%d' ]
				);

				if ( $result ) {
					++$updated;
				}
			}
		}

		return $updated;
	}

	/**
	 * Update options table references.
	 *
	 * @param string $oldUrl Old URL.
	 * @param string $newUrl New URL.
	 * @return int Number of options updated.
	 */
	private function updateOptionsReferences( string $oldUrl, string $newUrl ): int {
		global $wpdb;

		$updated = 0;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT option_id, option_value FROM {$wpdb->options} WHERE option_value LIKE %s",
				'%' . $wpdb->esc_like( $oldUrl ) . '%'
			),
			ARRAY_A
		);

		if ( empty( $rows ) ) {
			return 0;
		}

		foreach ( $rows as $row ) {
			$optionId    = (int) $row['option_id'];
			$optionValue = $row['option_value'];

			// Check if serialized.
			if ( $this->serializedHandler->isSerialized( $optionValue ) ) {
				$newValue = $this->serializedHandler->replaceInSerialized( $optionValue, $oldUrl, $newUrl );

				if ( $newValue === false ) {
					continue;
				}
			} else {
				$newValue = str_replace( $oldUrl, $newUrl, $optionValue );
			}

			if ( $newValue !== $optionValue ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$result = $wpdb->update(
					$wpdb->options,
					[ 'option_value' => $newValue ],
					[ 'option_id' => $optionId ],
					[ '%s' ],
					[ '%d' ]
				);

				if ( $result ) {
					++$updated;
				}
			}
		}

		return $updated;
	}

	/**
	 * Create backup of original file.
	 *
	 * @param int    $attachmentId Attachment ID.
	 * @param string $filePath     File path.
	 * @return bool Success.
	 */
	private function createBackup( int $attachmentId, string $filePath ): bool {
		$backupDir = OptimizationInstaller::getBackupDirectory();

		if ( ! is_dir( $backupDir ) ) {
			wp_mkdir_p( $backupDir );
		}

		$backupPath = $backupDir . '/' . $attachmentId . '_original_' . basename( $filePath );

		// Don't overwrite existing backup.
		if ( file_exists( $backupPath ) ) {
			return true;
		}

		return copy( $filePath, $backupPath );
	}

	/**
	 * Check if backup exists.
	 *
	 * @param int $attachmentId Attachment ID.
	 * @return bool True if backup exists.
	 */
	public function hasBackup( int $attachmentId ): bool {
		$backupDir = OptimizationInstaller::getBackupDirectory();
		$filePath  = \get_attached_file( $attachmentId );

		if ( ! $filePath ) {
			return false;
		}

		$backupPath = $backupDir . '/' . $attachmentId . '_original_' . basename( $filePath );

		return file_exists( $backupPath );
	}

	/**
	 * Restore backup.
	 *
	 * @param int $attachmentId Attachment ID.
	 * @return array{success: bool, message: string} Result.
	 */
	public function restoreBackup( int $attachmentId ): array {
		$backupDir = OptimizationInstaller::getBackupDirectory();
		$filePath  = \get_attached_file( $attachmentId );

		if ( ! $filePath ) {
			return [
				'success' => false,
				'message' => \__( 'Current file not found.', 'crispy-seo' ),
			];
		}

		$backupPath = $backupDir . '/' . $attachmentId . '_original_' . basename( $filePath );

		if ( ! file_exists( $backupPath ) ) {
			return [
				'success' => false,
				'message' => \__( 'No backup found.', 'crispy-seo' ),
			];
		}

		if ( ! copy( $backupPath, $filePath ) ) {
			return [
				'success' => false,
				'message' => \__( 'Failed to restore backup.', 'crispy-seo' ),
			];
		}

		// Regenerate thumbnails.
		$this->regenerateThumbnails( $attachmentId );

		// Clear optimization metadata.
		\delete_post_meta( $attachmentId, '_crispy_seo_optimized_at' );
		\delete_post_meta( $attachmentId, '_crispy_seo_replaced_at' );

		return [
			'success' => true,
			'message' => \__( 'Original file restored.', 'crispy-seo' ),
		];
	}

	/**
	 * AJAX: Replace media file.
	 */
	public function ajaxReplaceMedia(): void {
		\check_ajax_referer( 'crispy_seo_media_replace', 'nonce' );

		if ( ! \current_user_can( 'upload_files' ) ) {
			\wp_send_json_error( [ 'message' => \__( 'Permission denied.', 'crispy-seo' ) ] );
		}

		$attachmentId = isset( $_POST['attachment_id'] ) ? (int) $_POST['attachment_id'] : 0;

		if ( $attachmentId <= 0 ) {
			\wp_send_json_error( [ 'message' => \__( 'Invalid attachment ID.', 'crispy-seo' ) ] );
		}

		// Handle file upload.
		if ( empty( $_FILES['file'] ) ) {
			\wp_send_json_error( [ 'message' => \__( 'No file uploaded.', 'crispy-seo' ) ] );
		}

		$file = $_FILES['file'];

		// Validate upload.
		$uploadOverrides = [
			'test_form' => false,
			'mimes'     => get_allowed_mime_types(),
		];

		$uploadedFile = wp_handle_upload( $file, $uploadOverrides );

		if ( isset( $uploadedFile['error'] ) ) {
			\wp_send_json_error( [ 'message' => $uploadedFile['error'] ] );
		}

		// Replace the file.
		$result = $this->replaceFile( $attachmentId, $uploadedFile['file'], true );

		// Clean up temp file.
		if ( file_exists( $uploadedFile['file'] ) ) {
			wp_delete_file( $uploadedFile['file'] );
		}

		if ( $result['success'] ) {
			\wp_send_json_success( $result );
		} else {
			\wp_send_json_error( $result );
		}
	}

	/**
	 * AJAX: Restore media backup.
	 */
	public function ajaxRestoreMedia(): void {
		\check_ajax_referer( 'crispy_seo_media_replace', 'nonce' );

		if ( ! \current_user_can( 'upload_files' ) ) {
			\wp_send_json_error( [ 'message' => \__( 'Permission denied.', 'crispy-seo' ) ] );
		}

		$attachmentId = isset( $_POST['attachment_id'] ) ? (int) $_POST['attachment_id'] : 0;

		if ( $attachmentId <= 0 ) {
			\wp_send_json_error( [ 'message' => \__( 'Invalid attachment ID.', 'crispy-seo' ) ] );
		}

		$result = $this->restoreBackup( $attachmentId );

		if ( $result['success'] ) {
			\wp_send_json_success( $result );
		} else {
			\wp_send_json_error( $result );
		}
	}
}
