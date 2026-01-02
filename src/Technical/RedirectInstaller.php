<?php
/**
 * Redirect database table installer.
 *
 * @package CrispySEO
 * @since 2.0.0
 */

declare(strict_types=1);

namespace CrispySEO\Technical;

/**
 * Handles creation and updates of the redirects database table.
 */
class RedirectInstaller {

	/**
	 * Database version for tracking schema updates.
	 */
	private const DB_VERSION = '1.0.0';

	/**
	 * Option key for stored database version.
	 */
	private const VERSION_OPTION = 'crispy_seo_redirects_db_version';

	/**
	 * Get the table name with WordPress prefix.
	 *
	 * @return string Table name.
	 */
	public static function getTableName(): string {
		global $wpdb;
		return $wpdb->prefix . 'crispy_seo_redirects';
	}

	/**
	 * Install the database table.
	 *
	 * @return bool True on success, false on failure.
	 */
	public function install(): bool {
		$currentVersion = get_option( self::VERSION_OPTION, '0' );

		if ( version_compare( $currentVersion, self::DB_VERSION, '>=' ) ) {
			return true; // Already up to date.
		}

		$result = $this->createTable();

		if ( $result ) {
			update_option( self::VERSION_OPTION, self::DB_VERSION );

			// Migrate data from old options-based storage if exists.
			$this->migrateFromOptions();
		}

		return $result;
	}

	/**
	 * Create the redirects table.
	 *
	 * @return bool True on success.
	 */
	private function createTable(): bool {
		global $wpdb;

		$tableName = self::getTableName();
		$charset   = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$tableName} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			source_path VARCHAR(500) NOT NULL,
			target_url VARCHAR(2000) NOT NULL,
			redirect_type SMALLINT UNSIGNED NOT NULL DEFAULT 301,
			match_type ENUM('exact', 'wildcard', 'regex') NOT NULL DEFAULT 'exact',
			hit_count BIGINT UNSIGNED NOT NULL DEFAULT 0,
			last_hit DATETIME NULL,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			notes TEXT NULL,
			enabled TINYINT(1) NOT NULL DEFAULT 1,
			PRIMARY KEY (id),
			UNIQUE KEY source_path (source_path(191)),
			KEY redirect_type (redirect_type),
			KEY enabled (enabled),
			KEY match_type (match_type)
		) {$charset};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		// Verify table was created.
		$tableExists = $wpdb->get_var(
			$wpdb->prepare(
				'SHOW TABLES LIKE %s',
				$tableName
			)
		) === $tableName;

		return $tableExists;
	}

	/**
	 * Migrate redirects from options-based storage to database table.
	 *
	 * @return int Number of redirects migrated.
	 */
	private function migrateFromOptions(): int {
		$oldRedirects = get_option( 'crispy_seo_redirects', [] );

		if ( empty( $oldRedirects ) || ! is_array( $oldRedirects ) ) {
			return 0;
		}

		global $wpdb;
		$tableName = self::getTableName();
		$migrated  = 0;
		$now       = current_time( 'mysql' );

		foreach ( $oldRedirects as $redirect ) {
			if ( empty( $redirect['source'] ) || empty( $redirect['target'] ) ) {
				continue;
			}

			// Determine match type from old format.
			$source    = $redirect['source'];
			$matchType = 'exact';

			if ( str_starts_with( $source, '^' ) ) {
				$matchType = 'regex';
			} elseif ( str_ends_with( $source, '*' ) ) {
				$matchType = 'wildcard';
			}

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Migration operation.
			$inserted = $wpdb->insert(
				$tableName,
				[
					'source_path'   => sanitize_text_field( $source ),
					'target_url'    => esc_url_raw( $redirect['target'] ),
					'redirect_type' => absint( $redirect['type'] ?? 301 ),
					'match_type'    => $matchType,
					'hit_count'     => absint( $redirect['hits'] ?? 0 ),
					'created_at'    => $redirect['created'] ?? $now,
					'updated_at'    => $now,
					'enabled'       => 1,
				],
				[ '%s', '%s', '%d', '%s', '%d', '%s', '%s', '%d' ]
			);

			if ( $inserted ) {
				++$migrated;
			}
		}

		// Mark migration as complete but don't delete old data yet.
		if ( $migrated > 0 ) {
			update_option( 'crispy_seo_redirects_migrated', true );
		}

		return $migrated;
	}

	/**
	 * Uninstall the database table.
	 *
	 * @return bool True on success.
	 */
	public function uninstall(): bool {
		global $wpdb;

		$tableName = self::getTableName();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.SchemaChange -- Uninstall operation.
		$wpdb->query( "DROP TABLE IF EXISTS {$tableName}" );

		delete_option( self::VERSION_OPTION );
		delete_option( 'crispy_seo_redirects_migrated' );

		return true;
	}

	/**
	 * Check if the table exists.
	 *
	 * @return bool True if table exists.
	 */
	public function tableExists(): bool {
		global $wpdb;

		$tableName = self::getTableName();

		return $wpdb->get_var(
			$wpdb->prepare(
				'SHOW TABLES LIKE %s',
				$tableName
			)
		) === $tableName;
	}
}
