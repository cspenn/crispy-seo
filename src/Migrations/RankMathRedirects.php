<?php
/**
 * Rank Math Redirects Migration
 *
 * Imports redirects from Rank Math SEO plugin to Crispy SEO.
 *
 * @package CrispySEO\Migrations
 * @since 2.0.0
 */

declare(strict_types=1);

namespace CrispySEO\Migrations;

use CrispySEO\Technical\RedirectInstaller;

/**
 * Handles migration of redirects from Rank Math.
 */
class RankMathRedirects {

	/**
	 * Rank Math redirections table name.
	 */
	private const RANKMATH_TABLE = 'rank_math_redirections';

	/**
	 * Check if Rank Math redirects are available.
	 *
	 * @return bool True if Rank Math redirects exist.
	 */
	public function hasData(): bool {
		global $wpdb;

		$tableName = $wpdb->prefix . self::RANKMATH_TABLE;

		// Check if table exists.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Schema check.
		$exists = $wpdb->get_var(
			$wpdb->prepare( 'SHOW TABLES LIKE %s', $tableName )
		);

		if ( $exists !== $tableName ) {
			return false;
		}

		// Check if there are any redirects.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Count check.
		$count = $wpdb->get_var( "SELECT COUNT(*) FROM {$tableName}" );

		return (int) $count > 0;
	}

	/**
	 * Get count of Rank Math redirects.
	 *
	 * @return int Number of redirects.
	 */
	public function getCount(): int {
		global $wpdb;

		$tableName = $wpdb->prefix . self::RANKMATH_TABLE;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Count check.
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$tableName}" );
	}

	/**
	 * Migrate redirects from Rank Math.
	 *
	 * @param bool $dryRun If true, only count without importing.
	 * @return array{imported: int, skipped: int, errors: array<string>} Migration results.
	 */
	public function migrate( bool $dryRun = false ): array {
		global $wpdb;

		$rankMathTable = $wpdb->prefix . self::RANKMATH_TABLE;
		$crispyTable   = RedirectInstaller::getTableName();
		$now           = current_time( 'mysql' );

		$imported = 0;
		$skipped  = 0;
		$errors   = [];

		// Get all Rank Math redirects.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Migration query.
		$rankMathRedirects = $wpdb->get_results(
			"SELECT * FROM {$rankMathTable}",
			ARRAY_A
		);

		if ( empty( $rankMathRedirects ) ) {
			return [
				'imported' => 0,
				'skipped'  => 0,
				'errors'   => [ \__( 'No Rank Math redirects found.', 'crispy-seo' ) ],
			];
		}

		foreach ( $rankMathRedirects as $redirect ) {
			// Parse Rank Math sources (stored as serialized array).
			$sources = maybe_unserialize( $redirect['sources'] ?? '' );

			if ( ! is_array( $sources ) || empty( $sources ) ) {
				++$skipped;
				continue;
			}

			foreach ( $sources as $source ) {
				$pattern   = $source['pattern'] ?? '';
				$compare   = $source['comparison'] ?? 'exact';
				$target    = $redirect['url_to'] ?? '';
				$type      = (int) ( $redirect['header_code'] ?? 301 );
				$hits      = (int) ( $redirect['hits'] ?? 0 );
				$createdAt = $this->validateDateTime( $redirect['created'] ?? '' ) ?: $now;
				$updatedAt = $this->validateDateTime( $redirect['updated'] ?? '' ) ?: $now;

				if ( empty( $pattern ) || empty( $target ) ) {
					++$skipped;
					continue;
				}

				// Map Rank Math comparison types to Crispy SEO match types.
				$matchType = $this->mapMatchType( $compare );

				if ( $dryRun ) {
					++$imported;
					continue;
				}

				// Check for existing redirect with same source.
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Migration check.
				$existing = $wpdb->get_var(
					$wpdb->prepare(
						"SELECT id FROM {$crispyTable} WHERE source_path = %s",
						$pattern
					)
				);

				if ( $existing ) {
					$errors[] = sprintf(
						/* translators: %s: source path */
						\__( 'Skipped duplicate: %s', 'crispy-seo' ),
						$pattern
					);
					++$skipped;
					continue;
				}

				// Insert the redirect.
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Migration insert.
				$inserted = $wpdb->insert(
					$crispyTable,
					[
						'source_path'   => \sanitize_text_field( $pattern ),
						'target_url'    => esc_url_raw( $target ),
						'redirect_type' => $this->mapRedirectType( $type ),
						'match_type'    => $matchType,
						'hit_count'     => $hits,
						'created_at'    => $createdAt,
						'updated_at'    => $updatedAt,
						'notes'         => \__( 'Imported from Rank Math', 'crispy-seo' ),
						'enabled'       => 1,
					],
					[ '%s', '%s', '%d', '%s', '%d', '%s', '%s', '%s', '%d' ]
				);

				if ( $inserted ) {
					++$imported;
				} else {
					$errors[] = sprintf(
						/* translators: %s: source path */
						\__( 'Failed to import: %s', 'crispy-seo' ),
						$pattern
					);
					++$skipped;
				}
			}
		}

		// Mark migration as complete if not dry run.
		if ( ! $dryRun && $imported > 0 ) {
			\update_option( 'crispy_seo_rankmath_migrated', true );
		}

		return [
			'imported' => $imported,
			'skipped'  => $skipped,
			'errors'   => $errors,
		];
	}

	/**
	 * Map Rank Math comparison type to Crispy SEO match type.
	 *
	 * @param string $compare Rank Math comparison type.
	 * @return string Crispy SEO match type.
	 */
	private function mapMatchType( string $compare ): string {
		return match ( $compare ) {
			'exact'    => 'exact',
			'contains' => 'wildcard',
			'start'    => 'wildcard',
			'end'      => 'regex',
			'regex'    => 'regex',
			default    => 'exact',
		};
	}

	/**
	 * Map Rank Math redirect type to supported type.
	 *
	 * @param int $type Rank Math redirect type.
	 * @return int Crispy SEO redirect type.
	 */
	private function mapRedirectType( int $type ): int {
		$supported = [ 301, 302, 307, 308, 410, 451 ];

		if ( in_array( $type, $supported, true ) ) {
			return $type;
		}

		return 301;
	}

	/**
	 * Check if migration has already been completed.
	 *
	 * @return bool True if already migrated.
	 */
	public function isMigrated(): bool {
		return (bool) \get_option( 'crispy_seo_rankmath_migrated', false );
	}

	/**
	 * Reset migration status.
	 */
	public function resetMigration(): void {
		\delete_option( 'crispy_seo_rankmath_migrated' );
	}


	/**
	 * Validate a datetime string is in MySQL format.
	 *
	 * @param string $datetime Datetime string to validate.
	 * @return string|false The datetime string if valid, false otherwise.
	 */
	private function validateDateTime( string $datetime ): string|false {
		if ( empty( $datetime ) ) {
			return false;
		}

		// MySQL datetime format: YYYY-MM-DD HH:MM:SS.
		$parsed = \DateTime::createFromFormat( 'Y-m-d H:i:s', $datetime );

		if ( $parsed === false ) {
			return false;
		}

		// Ensure the formatted output matches the input (catches invalid dates like 2024-02-31).
		if ( $parsed->format( 'Y-m-d H:i:s' ) !== $datetime ) {
			return false;
		}

		return $datetime;
	}
}
