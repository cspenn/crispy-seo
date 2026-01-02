<?php
/**
 * Image Optimizer
 *
 * Handles image compression and WebP conversion using local PHP libraries.
 * No external API dependencies - uses GD or Imagick.
 *
 * @package CrispySEO\Media
 * @since 2.0.0
 */

declare(strict_types=1);

namespace CrispySEO\Media;

/**
 * Local image optimization and WebP conversion.
 */
class ImageOptimizer {

	/**
	 * Queue table name without prefix.
	 */
	private const QUEUE_TABLE = 'crispy_seo_optimization_queue';

	/**
	 * Default JPEG quality.
	 */
	private const DEFAULT_JPEG_QUALITY = 82;

	/**
	 * Default PNG compression level (0-9).
	 */
	private const DEFAULT_PNG_COMPRESSION = 6;

	/**
	 * Default WebP quality.
	 */
	private const DEFAULT_WEBP_QUALITY = 80;

	/**
	 * Batch size for cron processing.
	 */
	private const BATCH_SIZE = 10;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->initHooks();
	}

	/**
	 * Initialize WordPress hooks.
	 */
	private function initHooks(): void {
		// Auto-optimize on upload.
		\add_filter( 'wp_generate_attachment_metadata', [ $this, 'optimizeOnUpload' ], 10, 2 );

		// Admin hooks.
		if ( \is_admin() ) {
			\add_action( 'admin_menu', [ $this, 'registerAdminPage' ] );
			\add_action( 'wp_ajax_crispy_seo_optimize_image', [ $this, 'ajaxOptimizeImage' ] );
			\add_action( 'wp_ajax_crispy_seo_queue_all_images', [ $this, 'ajaxQueueAll' ] );
			\add_action( 'wp_ajax_crispy_seo_process_queue', [ $this, 'ajaxProcessQueue' ] );
			\add_action( 'wp_ajax_crispy_seo_get_optimization_stats', [ $this, 'ajaxGetStats' ] );

			// Add media library column.
			\add_filter( 'manage_media_columns', [ $this, 'addMediaColumn' ] );
			\add_action( 'manage_media_custom_column', [ $this, 'renderMediaColumn' ], 10, 2 );
		}

		// WP-Cron batch processing.
		\add_action( 'crispy_seo_process_image_queue', [ $this, 'processQueueBatch' ] );

		if ( ! \wp_next_scheduled( 'crispy_seo_process_image_queue' ) ) {
			\wp_schedule_event( time(), 'hourly', 'crispy_seo_process_image_queue' );
		}
	}

	/**
	 * Register admin page.
	 */
	public function registerAdminPage(): void {
		add_submenu_page(
			'crispy-seo',
			\__( 'Image Optimization', 'crispy-seo' ),
			\__( 'Image Optimization', 'crispy-seo' ),
			'manage_options',
			'crispy-seo-image-optimization',
			[ $this, 'renderAdminPage' ]
		);
	}

	/**
	 * Render admin page.
	 */
	public function renderAdminPage(): void {
		if ( ! \current_user_can( 'manage_options' ) ) {
			return;
		}
		include CRISPY_SEO_DIR . 'views/admin-image-optimization.php';
	}

	/**
	 * Optimize image on upload.
	 *
	 * @param array $metadata      Attachment metadata.
	 * @param int   $attachmentId  Attachment ID.
	 * @return array Modified metadata.
	 */
	public function optimizeOnUpload( array $metadata, int $attachmentId ): array {
		// Check if auto-optimization is enabled.
		$autoOptimize = \get_option( 'crispy_seo_auto_optimize', true );

		if ( ! $autoOptimize ) {
			return $metadata;
		}

		// Only optimize images.
		$mimeType = get_post_mime_type( $attachmentId );
		if ( ! $this->isOptimizableImage( $mimeType ) ) {
			return $metadata;
		}

		// Queue for background processing instead of blocking upload.
		$this->queueOptimization( $attachmentId );

		return $metadata;
	}

	/**
	 * Check if image is optimizable.
	 *
	 * @param string $mimeType MIME type.
	 * @return bool True if optimizable.
	 */
	private function isOptimizableImage( string $mimeType ): bool {
		$optimizable = [ 'image/jpeg', 'image/png', 'image/gif', 'image/webp' ];
		return in_array( $mimeType, $optimizable, true );
	}

	/**
	 * Queue an image for optimization.
	 *
	 * @param int    $attachmentId    Attachment ID.
	 * @param string $optimizationType Type: 'compress', 'webp', or 'both'.
	 * @return bool Success.
	 */
	public function queueOptimization( int $attachmentId, string $optimizationType = 'both' ): bool {
		global $wpdb;

		$tableName = $wpdb->prefix . self::QUEUE_TABLE;

		// Get original file size.
		$filePath = \get_attached_file( $attachmentId );
		if ( ! $filePath || ! file_exists( $filePath ) ) {
			return false;
		}

		$originalSize = filesize( $filePath );

		// Check if already queued.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$existing = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$tableName} WHERE attachment_id = %d AND status IN ('pending', 'processing')",
				$attachmentId
			)
		);

		if ( $existing ) {
			return true; // Already queued.
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$inserted = $wpdb->insert(
			$tableName,
			[
				'attachment_id'     => $attachmentId,
				'status'            => 'pending',
				'optimization_type' => $optimizationType,
				'original_size'     => $originalSize,
				'created_at'        => current_time( 'mysql' ),
			],
			[ '%d', '%s', '%s', '%d', '%s' ]
		);

		return (bool) $inserted;
	}

	/**
	 * Optimize a single image.
	 *
	 * @param int   $attachmentId Attachment ID.
	 * @param array $options      Optimization options.
	 * @return array{success: bool, message: string, savings_bytes: int, savings_percent: float} Result.
	 */
	public function optimizeImage( int $attachmentId, array $options = [] ): array {
		$defaults = [
			'jpeg_quality'    => (int) \get_option( 'crispy_seo_jpeg_quality', self::DEFAULT_JPEG_QUALITY ),
			'png_compression' => (int) \get_option( 'crispy_seo_png_compression', self::DEFAULT_PNG_COMPRESSION ),
			'webp_quality'    => (int) \get_option( 'crispy_seo_webp_quality', self::DEFAULT_WEBP_QUALITY ),
			'create_webp'     => (bool) \get_option( 'crispy_seo_create_webp', true ),
			'backup'          => true,
		];

		$options = \wp_parse_args( $options, $defaults );

		$filePath = \get_attached_file( $attachmentId );

		if ( ! $filePath || ! file_exists( $filePath ) ) {
			return [
				'success'         => false,
				'message'         => \__( 'File not found.', 'crispy-seo' ),
				'savings_bytes'   => 0,
				'savings_percent' => 0.0,
			];
		}

		$mimeType     = get_post_mime_type( $attachmentId );
		$originalSize = filesize( $filePath );

		// Create backup if enabled.
		if ( $options['backup'] ) {
			$this->createBackup( $attachmentId, $filePath );
		}

		// Optimize based on image type.
		$result = match ( $mimeType ) {
			'image/jpeg' => $this->optimizeJpeg( $filePath, $options['jpeg_quality'] ),
			'image/png'  => $this->optimizePng( $filePath, $options['png_compression'] ),
			'image/gif'  => $this->optimizeGif( $filePath ),
			default      => [ 'success' => false, 'message' => \__( 'Unsupported image type.', 'crispy-seo' ) ],
		};

		if ( ! $result['success'] ) {
			return array_merge( $result, [ 'savings_bytes' => 0, 'savings_percent' => 0.0 ] );
		}

		// Create WebP version if enabled.
		if ( $options['create_webp'] && in_array( $mimeType, [ 'image/jpeg', 'image/png' ], true ) ) {
			$this->createWebP( $filePath, $options['webp_quality'] );
		}

		// Calculate savings.
		clearstatcache( true, $filePath );
		$newSize        = filesize( $filePath );
		$savingsBytes   = $originalSize - $newSize;
		$savingsPercent = $originalSize > 0 ? round( ( $savingsBytes / $originalSize ) * 100, 2 ) : 0.0;

		// Update attachment metadata.
		$this->updateOptimizationMeta( $attachmentId, $originalSize, $newSize );

		return [
			'success'         => true,
			'message'         => sprintf(
				/* translators: %s: percentage saved */
				\__( 'Optimized successfully. Saved %s%%', 'crispy-seo' ),
				$savingsPercent
			),
			'savings_bytes'   => $savingsBytes,
			'savings_percent' => $savingsPercent,
		];
	}

	/**
	 * Optimize JPEG image.
	 *
	 * @param string $filePath File path.
	 * @param int    $quality  JPEG quality (1-100).
	 * @return array{success: bool, message: string} Result.
	 */
	private function optimizeJpeg( string $filePath, int $quality ): array {
		// Try Imagick first.
		if ( extension_loaded( 'imagick' ) ) {
			try {
				$image = new \Imagick( $filePath );
				$image->setImageCompressionQuality( $quality );
				$image->stripImage(); // Remove metadata.
				$image->writeImage( $filePath );
				$image->destroy();

				return [ 'success' => true, 'message' => '' ];
			} catch ( \ImagickException $e ) {
				// Fall through to GD.
			}
		}

		// Fall back to GD.
		if ( function_exists( 'imagecreatefromjpeg' ) ) {
			$image = imagecreatefromjpeg( $filePath );

			if ( $image === false ) {
				return [ 'success' => false, 'message' => \__( 'Failed to read JPEG.', 'crispy-seo' ) ];
			}

			$result = imagejpeg( $image, $filePath, $quality );
			imagedestroy( $image );

			if ( $result ) {
				return [ 'success' => true, 'message' => '' ];
			}
		}

		return [ 'success' => false, 'message' => \__( 'No image library available.', 'crispy-seo' ) ];
	}

	/**
	 * Optimize PNG image.
	 *
	 * @param string $filePath    File path.
	 * @param int    $compression PNG compression (0-9).
	 * @return array{success: bool, message: string} Result.
	 */
	private function optimizePng( string $filePath, int $compression ): array {
		// Try Imagick first.
		if ( extension_loaded( 'imagick' ) ) {
			try {
				$image = new \Imagick( $filePath );
				$image->setImageCompressionQuality( $compression * 10 );
				$image->stripImage();
				$image->writeImage( $filePath );
				$image->destroy();

				return [ 'success' => true, 'message' => '' ];
			} catch ( \ImagickException $e ) {
				// Fall through to GD.
			}
		}

		// Fall back to GD.
		if ( function_exists( 'imagecreatefrompng' ) ) {
			$image = imagecreatefrompng( $filePath );

			if ( $image === false ) {
				return [ 'success' => false, 'message' => \__( 'Failed to read PNG.', 'crispy-seo' ) ];
			}

			// Preserve transparency.
			imagesavealpha( $image, true );

			$result = imagepng( $image, $filePath, $compression );
			imagedestroy( $image );

			if ( $result ) {
				return [ 'success' => true, 'message' => '' ];
			}
		}

		return [ 'success' => false, 'message' => \__( 'No image library available.', 'crispy-seo' ) ];
	}

	/**
	 * Optimize GIF image (strip metadata only).
	 *
	 * @param string $filePath File path.
	 * @return array{success: bool, message: string} Result.
	 */
	private function optimizeGif( string $filePath ): array {
		// GIF optimization is limited - just strip metadata with Imagick if available.
		if ( extension_loaded( 'imagick' ) ) {
			try {
				$image = new \Imagick( $filePath );
				$image->stripImage();
				$image->writeImage( $filePath );
				$image->destroy();

				return [ 'success' => true, 'message' => '' ];
			} catch ( \ImagickException $e ) {
				return [ 'success' => false, 'message' => $e->getMessage() ];
			}
		}

		// GD cannot efficiently optimize GIF.
		return [ 'success' => true, 'message' => \__( 'GIF optimization limited without Imagick.', 'crispy-seo' ) ];
	}

	/**
	 * Create WebP version of an image.
	 *
	 * @param string $filePath File path.
	 * @param int    $quality  WebP quality (1-100).
	 * @return string|false WebP file path or false on failure.
	 */
	public function createWebP( string $filePath, int $quality ): string|false {
		$webpPath = preg_replace( '/\.(jpe?g|png)$/i', '.webp', $filePath );

		if ( $webpPath === null || $webpPath === $filePath ) {
			return false;
		}

		// Try Imagick first.
		if ( extension_loaded( 'imagick' ) ) {
			try {
				$image = new \Imagick( $filePath );
				$image->setImageFormat( 'webp' );
				$image->setImageCompressionQuality( $quality );
				$image->writeImage( $webpPath );
				$image->destroy();

				return $webpPath;
			} catch ( \ImagickException $e ) {
				// Fall through to GD.
			}
		}

		// Fall back to GD.
		if ( function_exists( 'imagewebp' ) ) {
			$mimeType = mime_content_type( $filePath );

			// mime_content_type can return false if file is unreadable.
			if ( $mimeType === false ) {
				return false;
			}

			$image = match ( $mimeType ) {
				'image/jpeg' => imagecreatefromjpeg( $filePath ),
				'image/png'  => imagecreatefrompng( $filePath ),
				default      => false,
			};

			if ( $image === false ) {
				return false;
			}

			// Handle PNG transparency.
			if ( $mimeType === 'image/png' ) {
				imagesavealpha( $image, true );
			}

			$result = imagewebp( $image, $webpPath, $quality );
			imagedestroy( $image );

			if ( $result ) {
				return $webpPath;
			}
		}

		return false;
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

		$backupPath = $backupDir . '/' . $attachmentId . '_' . basename( $filePath );

		// Don't overwrite existing backup.
		if ( file_exists( $backupPath ) ) {
			return true;
		}

		return copy( $filePath, $backupPath );
	}

	/**
	 * Update optimization metadata.
	 *
	 * @param int $attachmentId Attachment ID.
	 * @param int $originalSize Original file size.
	 * @param int $newSize      New file size.
	 */
	private function updateOptimizationMeta( int $attachmentId, int $originalSize, int $newSize ): void {
		\update_post_meta( $attachmentId, '_crispy_seo_original_size', $originalSize );
		\update_post_meta( $attachmentId, '_crispy_seo_optimized_size', $newSize );
		\update_post_meta( $attachmentId, '_crispy_seo_optimized_at', current_time( 'mysql' ) );
	}

	/**
	 * Process a batch of queued images.
	 *
	 * @param int $limit Number of images to process.
	 * @return array{processed: int, failed: int} Results.
	 */
	public function processQueueBatch( int $limit = self::BATCH_SIZE ): array {
		global $wpdb;

		$tableName = $wpdb->prefix . self::QUEUE_TABLE;
		$processed = 0;
		$failed    = 0;

		// Get pending items.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$items = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$tableName} WHERE status = 'pending' ORDER BY created_at ASC LIMIT %d",
				$limit
			),
			ARRAY_A
		);

		if ( empty( $items ) ) {
			return [ 'processed' => 0, 'failed' => 0 ];
		}

		foreach ( $items as $item ) {
			// Mark as processing.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->update(
				$tableName,
				[ 'status' => 'processing' ],
				[ 'id' => (int) $item['id'] ],
				[ '%s' ],
				[ '%d' ]
			);

			$result = $this->optimizeImage( (int) $item['attachment_id'] );

			if ( $result['success'] ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$wpdb->update(
					$tableName,
					[
						'status'          => 'completed',
						'optimized_size'  => $item['original_size'] - $result['savings_bytes'],
						'savings_percent' => $result['savings_percent'],
						'processed_at'    => current_time( 'mysql' ),
					],
					[ 'id' => (int) $item['id'] ],
					[ '%s', '%d', '%f', '%s' ],
					[ '%d' ]
				);
				++$processed;
			} else {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$wpdb->update(
					$tableName,
					[
						'status'        => 'failed',
						'error_message' => $result['message'],
						'processed_at'  => current_time( 'mysql' ),
					],
					[ 'id' => (int) $item['id'] ],
					[ '%s', '%s', '%s' ],
					[ '%d' ]
				);
				++$failed;
			}
		}

		return [ 'processed' => $processed, 'failed' => $failed ];
	}

	/**
	 * Get optimization statistics.
	 *
	 * @return array Statistics.
	 */
	public function getStats(): array {
		global $wpdb;

		$tableName = $wpdb->prefix . self::QUEUE_TABLE;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$queueStats = $wpdb->get_row(
			"SELECT
				COUNT(*) as total,
				SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
				SUM(CASE WHEN status = 'processing' THEN 1 ELSE 0 END) as processing,
				SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
				SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed,
				SUM(original_size) as total_original,
				SUM(optimized_size) as total_optimized,
				AVG(savings_percent) as avg_savings
			FROM {$tableName}",
			ARRAY_A
		);

		// Get total images in library.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$totalImages = $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->posts}
			 WHERE post_type = 'attachment'
			 AND post_mime_type IN ('image/jpeg', 'image/png', 'image/gif')"
		);

		// Get unoptimized count.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$optimizedCount = $wpdb->get_var(
			"SELECT COUNT(DISTINCT post_id) FROM {$wpdb->postmeta}
			 WHERE meta_key = '_crispy_seo_optimized_at'"
		);

		return [
			'total_images'     => (int) $totalImages,
			'optimized_count'  => (int) $optimizedCount,
			'unoptimized'      => (int) $totalImages - (int) $optimizedCount,
			'queue_pending'    => (int) ( $queueStats['pending'] ?? 0 ),
			'queue_processing' => (int) ( $queueStats['processing'] ?? 0 ),
			'queue_completed'  => (int) ( $queueStats['completed'] ?? 0 ),
			'queue_failed'     => (int) ( $queueStats['failed'] ?? 0 ),
			'total_saved'      => (int) ( $queueStats['total_original'] ?? 0 ) - (int) ( $queueStats['total_optimized'] ?? 0 ),
			'avg_savings'      => round( (float) ( $queueStats['avg_savings'] ?? 0 ), 2 ),
		];
	}

	/**
	 * Add media library column.
	 *
	 * @param array $columns Existing columns.
	 * @return array Modified columns.
	 */
	public function addMediaColumn( array $columns ): array {
		$columns['crispy_optimization'] = \__( 'Optimization', 'crispy-seo' );
		return $columns;
	}

	/**
	 * Render media library column.
	 *
	 * @param string $columnName Column name.
	 * @param int    $postId     Post ID.
	 */
	public function renderMediaColumn( string $columnName, int $postId ): void {
		if ( $columnName !== 'crispy_optimization' ) {
			return;
		}

		$mimeType = get_post_mime_type( $postId );

		if ( ! $this->isOptimizableImage( $mimeType ) ) {
			echo '—';
			return;
		}

		$optimizedAt = \get_post_meta( $postId, '_crispy_seo_optimized_at', true );

		if ( $optimizedAt ) {
			$originalSize  = (int) \get_post_meta( $postId, '_crispy_seo_original_size', true );
			$optimizedSize = (int) \get_post_meta( $postId, '_crispy_seo_optimized_size', true );
			$savings       = $originalSize > 0 ? round( ( ( $originalSize - $optimizedSize ) / $originalSize ) * 100, 1 ) : 0;

			printf(
				'<span class="dashicons dashicons-yes" style="color: green;"></span> %s%%',
				\esc_html( $savings )
			);
		} else {
			printf(
				'<button type="button" class="button button-small crispy-optimize-btn" data-id="%d">%s</button>',
				\esc_attr( $postId ),
				esc_html\__( 'Optimize', 'crispy-seo' )
			);
		}
	}

	/**
	 * AJAX: Optimize single image.
	 */
	public function ajaxOptimizeImage(): void {
		\check_ajax_referer( 'crispy_seo_image_optimization', 'nonce' );

		if ( ! \current_user_can( 'upload_files' ) ) {
			\wp_send_json_error( [ 'message' => \__( 'Permission denied.', 'crispy-seo' ) ] );
		}

		$attachmentId = isset( $_POST['attachment_id'] ) ? (int) $_POST['attachment_id'] : 0;

		if ( $attachmentId <= 0 ) {
			\wp_send_json_error( [ 'message' => \__( 'Invalid attachment ID.', 'crispy-seo' ) ] );
		}

		$result = $this->optimizeImage( $attachmentId );

		if ( $result['success'] ) {
			\wp_send_json_success( $result );
		} else {
			\wp_send_json_error( $result );
		}
	}

	/**
	 * AJAX: Queue all unoptimized images.
	 */
	public function ajaxQueueAll(): void {
		\check_ajax_referer( 'crispy_seo_image_optimization', 'nonce' );

		if ( ! \current_user_can( 'manage_options' ) ) {
			\wp_send_json_error( [ 'message' => \__( 'Permission denied.', 'crispy-seo' ) ] );
		}

		global $wpdb;

		// Get unoptimized images.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$attachmentIds = $wpdb->get_col(
			"SELECT p.ID FROM {$wpdb->posts} p
			 LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_crispy_seo_optimized_at'
			 WHERE p.post_type = 'attachment'
			 AND p.post_mime_type IN ('image/jpeg', 'image/png', 'image/gif')
			 AND pm.meta_id IS NULL
			 LIMIT 1000"
		);

		$queued = 0;
		foreach ( $attachmentIds as $id ) {
			if ( $this->queueOptimization( (int) $id ) ) {
				++$queued;
			}
		}

		\wp_send_json_success(
			[
				'message' => sprintf(
					/* translators: %d: number of images queued */
					\__( '%d images queued for optimization.', 'crispy-seo' ),
					$queued
				),
				'queued'  => $queued,
			]
		);
	}

	/**
	 * AJAX: Process queue batch.
	 */
	public function ajaxProcessQueue(): void {
		\check_ajax_referer( 'crispy_seo_image_optimization', 'nonce' );

		if ( ! \current_user_can( 'manage_options' ) ) {
			\wp_send_json_error( [ 'message' => \__( 'Permission denied.', 'crispy-seo' ) ] );
		}

		$result = $this->processQueueBatch( 5 );

		\wp_send_json_success(
			[
				'processed' => $result['processed'],
				'failed'    => $result['failed'],
				'stats'     => $this->getStats(),
			]
		);
	}

	/**
	 * AJAX: Get optimization stats.
	 */
	public function ajaxGetStats(): void {
		\check_ajax_referer( 'crispy_seo_image_optimization', 'nonce' );

		if ( ! \current_user_can( 'manage_options' ) ) {
			\wp_send_json_error( [ 'message' => \__( 'Permission denied.', 'crispy-seo' ) ] );
		}

		\wp_send_json_success( [ 'stats' => $this->getStats() ] );
	}

	/**
	 * Check available image libraries.
	 *
	 * @return array{imagick: bool, gd: bool, webp: bool} Available libraries.
	 */
	public function getAvailableLibraries(): array {
		return [
			'imagick' => extension_loaded( 'imagick' ),
			'gd'      => extension_loaded( 'gd' ),
			'webp'    => function_exists( 'imagewebp' ) || ( extension_loaded( 'imagick' ) && \Imagick::queryFormats( 'WEBP' ) ),
		];
	}
}
