<?php
/**
 * Internal Link Juicer Migration
 *
 * Imports keyword configurations from Internal Link Juicer Pro plugin.
 *
 * @package CrispySEO\Migrations
 * @since 2.0.0
 */

declare(strict_types=1);

namespace CrispySEO\Migrations;

/**
 * Handles migration of keywords from Internal Link Juicer.
 */
class InternalLinkJuicer {

	/**
	 * ILJ post type for link definitions.
	 */
	private const ILJ_POST_TYPE = 'ilj_linkdefinition';

	/**
	 * ILJ meta key for keywords.
	 */
	private const ILJ_META_KEY = '_ilj_linkdefinition_keywords';

	/**
	 * Crispy SEO keywords table.
	 */
	private const CRISPY_TABLE = 'crispy_seo_link_keywords';

	/**
	 * Check if Internal Link Juicer data is available.
	 *
	 * @return bool True if ILJ data exists.
	 */
	public function hasData(): bool {
		// Check if ILJ post type exists.
		$posts = \get_posts(
			[
				'post_type'      => self::ILJ_POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => 1,
				'fields'         => 'ids',
			]
		);

		return ! empty( $posts );
	}

	/**
	 * Get count of ILJ link definitions.
	 *
	 * @return int Number of link definitions.
	 */
	public function getCount(): int {
		$posts = \get_posts(
			[
				'post_type'      => self::ILJ_POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'fields'         => 'ids',
			]
		);

		return count( $posts );
	}

	/**
	 * Migrate keywords from Internal Link Juicer.
	 *
	 * @param bool $dryRun If true, only count without importing.
	 * @return array{imported: int, skipped: int, errors: array<string>} Migration results.
	 */
	public function migrate( bool $dryRun = false ): array {
		global $wpdb;

		$crispyTable = $wpdb->prefix . self::CRISPY_TABLE;
		$now         = current_time( 'mysql' );

		$imported = 0;
		$skipped  = 0;
		$errors   = [];

		// Get all ILJ link definitions.
		$iljPosts = \get_posts(
			[
				'post_type'      => self::ILJ_POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => -1,
			]
		);

		if ( empty( $iljPosts ) ) {
			return [
				'imported' => 0,
				'skipped'  => 0,
				'errors'   => [ \__( 'No Internal Link Juicer data found.', 'crispy-seo' ) ],
			];
		}

		foreach ( $iljPosts as $iljPost ) {
			// Get the target post ID.
			$targetPostId = $this->getTargetPostId( $iljPost );

			if ( ! $targetPostId ) {
				$errors[] = sprintf(
					/* translators: %d: ILJ post ID */
					\__( 'Could not determine target for ILJ definition #%d', 'crispy-seo' ),
					$iljPost->ID
				);
				++$skipped;
				continue;
			}

			// Get keywords from ILJ.
			$keywords = \get_post_meta( $iljPost->ID, self::ILJ_META_KEY, true );

			if ( empty( $keywords ) ) {
				// Try alternative storage method.
				$keywords = $this->extractKeywordsFromTitle( $iljPost );
			}

			if ( empty( $keywords ) ) {
				++$skipped;
				continue;
			}

			// Normalize keywords to array.
			if ( is_string( $keywords ) ) {
				$keywords = [ $keywords ];
			} elseif ( is_object( $keywords ) ) {
				$keywords = (array) $keywords;
			}

			foreach ( $keywords as $keyword ) {
				if ( empty( $keyword ) || ! is_string( $keyword ) ) {
					continue;
				}

				$keyword = trim( $keyword );

				if ( strlen( $keyword ) < 2 ) {
					continue;
				}

				if ( $dryRun ) {
					++$imported;
					continue;
				}

				// Check for existing keyword.
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$existing = $wpdb->get_var(
					$wpdb->prepare(
						"SELECT id FROM {$crispyTable} WHERE keyword = %s",
						$keyword
					)
				);

				if ( $existing ) {
					$errors[] = sprintf(
						/* translators: %s: keyword */
						\__( 'Skipped duplicate keyword: %s', 'crispy-seo' ),
						$keyword
					);
					++$skipped;
					continue;
				}

				// Insert the keyword.
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
				$inserted = $wpdb->insert(
					$crispyTable,
					[
						'keyword'            => \sanitize_text_field( $keyword ),
						'target_post_id'     => $targetPostId,
						'anchor_text'        => '', // ILJ uses keyword as anchor.
						'max_links_per_page' => 3,
						'case_sensitive'     => 0,
						'priority'           => 10,
						'enabled'            => 1,
						'created_at'         => $now,
					],
					[ '%s', '%d', '%s', '%d', '%d', '%d', '%d', '%s' ]
				);

				if ( $inserted ) {
					++$imported;
				} else {
					$errors[] = sprintf(
						/* translators: %s: keyword */
						\__( 'Failed to import keyword: %s', 'crispy-seo' ),
						$keyword
					);
					++$skipped;
				}
			}
		}

		// Mark migration as complete if not dry run.
		if ( ! $dryRun && $imported > 0 ) {
			\update_option( 'crispy_seo_ilj_migrated', true );
		}

		return [
			'imported' => $imported,
			'skipped'  => $skipped,
			'errors'   => $errors,
		];
	}

	/**
	 * Get target post ID from ILJ definition.
	 *
	 * @param \WP_Post $iljPost ILJ post object.
	 * @return int|null Target post ID or null.
	 */
	private function getTargetPostId( \WP_Post $iljPost ): ?int {
		// ILJ stores target in different ways depending on version.

		// Method 1: Post meta.
		$targetId = \get_post_meta( $iljPost->ID, '_ilj_linkdefinition_type_post', true );
		if ( $targetId && is_numeric( $targetId ) ) {
			return (int) $targetId;
		}

		// Method 2: Post parent relationship.
		if ( $iljPost->post_parent > 0 ) {
			return $iljPost->post_parent;
		}

		// Method 3: Stored as serialized data.
		$typeData = \get_post_meta( $iljPost->ID, '_ilj_linkdefinition_type', true );
		if ( is_array( $typeData ) && isset( $typeData['id'] ) ) {
			return (int) $typeData['id'];
		}

		// Method 4: Look for post meta with post ID pattern.
		$allMeta = \get_post_meta( $iljPost->ID );
		foreach ( $allMeta as $key => $value ) {
			if ( strpos( $key, 'post' ) !== false && is_array( $value ) ) {
				$val = reset( $value );
				if ( is_numeric( $val ) && \get_post( (int) $val ) ) {
					return (int) $val;
				}
			}
		}

		return null;
	}

	/**
	 * Extract keywords from ILJ post title as fallback.
	 *
	 * @param \WP_Post $iljPost ILJ post object.
	 * @return array<string> Keywords.
	 */
	private function extractKeywordsFromTitle( \WP_Post $iljPost ): array {
		$title = $iljPost->post_title;

		if ( empty( $title ) ) {
			return [];
		}

		// ILJ sometimes stores keywords in title separated by commas or pipes.
		$keywords = preg_split( '/[,|]/', $title );

		if ( $keywords === false ) {
			return [ $title ];
		}

		return array_filter( array_map( 'trim', $keywords ) );
	}

	/**
	 * Check if migration has already been completed.
	 *
	 * @return bool True if already migrated.
	 */
	public function isMigrated(): bool {
		return (bool) \get_option( 'crispy_seo_ilj_migrated', false );
	}

	/**
	 * Reset migration status.
	 */
	public function resetMigration(): void {
		\delete_option( 'crispy_seo_ilj_migrated' );
	}
}
