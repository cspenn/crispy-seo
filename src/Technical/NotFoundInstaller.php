<?php
/**
 * 404 Log database table installer.
 *
 * @package CrispySEO
 * @since 2.1.0
 */

declare(strict_types=1);

namespace CrispySEO\Technical;

/**
 * Handles creation and maintenance of the 404 logs database table.
 */
class NotFoundInstaller {

	/**
	 * Database version for tracking schema updates.
	 */
	private const DB_VERSION = '1.0.0';

	/**
	 * Option key for stored database version.
	 */
	private const VERSION_OPTION = 'crispy_seo_404_db_version';

	/**
	 * Get the table name with WordPress prefix.
	 *
	 * @return string Table name.
	 */
	public static function getTableName(): string {
		global $wpdb;
		return $wpdb->prefix . 'crispy_seo_404_logs';
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
		}

		return $result;
	}

	/**
	 * Create the 404 logs table.
	 *
	 * @return bool True on success.
	 */
	private function createTable(): bool {
		global $wpdb;

		$tableName = self::getTableName();
		$charset   = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$tableName} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			request_path VARCHAR(500) NOT NULL,
			request_query VARCHAR(1000) NULL,
			referrer VARCHAR(2000) NULL,
			user_agent VARCHAR(500) NULL,
			ip_address VARCHAR(45) NULL,
			created_at DATETIME NOT NULL,
			PRIMARY KEY (id),
			KEY request_path (request_path(191)),
			KEY created_at (created_at)
		) {$charset};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		// Verify table was created.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Installation check.
		$tableExists = $wpdb->get_var(
			$wpdb->prepare(
				'SHOW TABLES LIKE %s',
				$tableName
			)
		) === $tableName;

		return $tableExists;
	}

	/**
	 * Uninstall the database table.
	 *
	 * @return bool True on success.
	 */
	public function uninstall(): bool {
		global $wpdb;

		$tableName = self::getTableName();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.DirectDatabaseQuery.NoCaching -- Uninstall operation.
		$wpdb->query( "DROP TABLE IF EXISTS {$tableName}" );

		delete_option( self::VERSION_OPTION );
		delete_option( 'crispy_seo_404_page_id' );
		delete_option( 'crispy_seo_404_log_retention_days' );
		delete_option( 'crispy_seo_404_log_enabled' );

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

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Check operation.
		return $wpdb->get_var(
			$wpdb->prepare(
				'SHOW TABLES LIKE %s',
				$tableName
			)
		) === $tableName;
	}

	/**
	 * Clean up old log entries.
	 *
	 * @param int $daysToKeep Number of days to keep logs (default 30).
	 * @return int Number of deleted records.
	 */
	public function cleanupOldLogs( int $daysToKeep = 30 ): int {
		global $wpdb;

		$tableName = self::getTableName();
		$cutoffDate = gmdate( 'Y-m-d H:i:s', strtotime( "-{$daysToKeep} days" ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Cleanup operation.
		$deleted = $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$tableName} WHERE created_at < %s",
				$cutoffDate
			)
		);

		return is_int( $deleted ) ? $deleted : 0;
	}
}
