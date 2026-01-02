<?php
/**
 * Internal link database tables installer.
 *
 * @package CrispySEO
 * @since 2.0.0
 */

declare(strict_types=1);

namespace CrispySEO\Content;

/**
 * Handles creation and updates of internal link database tables.
 */
class LinkInstaller {

	/**
	 * Database version for tracking schema updates.
	 */
	private const DB_VERSION = '1.0.0';

	/**
	 * Option key for stored database version.
	 */
	private const VERSION_OPTION = 'crispy_seo_links_db_version';

	/**
	 * Get the keywords table name with WordPress prefix.
	 *
	 * @return string Table name.
	 */
	public static function getKeywordsTableName(): string {
		global $wpdb;
		return $wpdb->prefix . 'crispy_seo_link_keywords';
	}

	/**
	 * Get the link index table name with WordPress prefix.
	 *
	 * @return string Table name.
	 */
	public static function getIndexTableName(): string {
		global $wpdb;
		return $wpdb->prefix . 'crispy_seo_link_index';
	}

	/**
	 * Install the database tables.
	 *
	 * @return bool True on success, false on failure.
	 */
	public function install(): bool {
		$currentVersion = \get_option( self::VERSION_OPTION, '0' );

		if ( version_compare( $currentVersion, self::DB_VERSION, '>=' ) ) {
			return true; // Already up to date.
		}

		$keywordsCreated = $this->createKeywordsTable();
		$indexCreated    = $this->createIndexTable();

		if ( $keywordsCreated && $indexCreated ) {
			\update_option( self::VERSION_OPTION, self::DB_VERSION );
			return true;
		}

		return false;
	}

	/**
	 * Create the keywords table.
	 *
	 * @return bool True on success.
	 */
	private function createKeywordsTable(): bool {
		global $wpdb;

		$tableName = self::getKeywordsTableName();
		$charset   = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$tableName} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			keyword VARCHAR(255) NOT NULL,
			target_post_id BIGINT UNSIGNED NOT NULL,
			anchor_text VARCHAR(255) NULL,
			max_links_per_page INT UNSIGNED NOT NULL DEFAULT 3,
			case_sensitive TINYINT(1) NOT NULL DEFAULT 0,
			priority INT NOT NULL DEFAULT 10,
			enabled TINYINT(1) NOT NULL DEFAULT 1,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY (id),
			KEY keyword (keyword(191)),
			KEY target_post_id (target_post_id),
			KEY enabled (enabled),
			KEY priority (priority)
		) {$charset};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		\dbDelta( $sql );

		// Verify table was created.
		return $wpdb->get_var(
			$wpdb->prepare(
				'SHOW TABLES LIKE %s',
				$tableName
			)
		) === $tableName;
	}

	/**
	 * Create the link index table.
	 *
	 * @return bool True on success.
	 */
	private function createIndexTable(): bool {
		global $wpdb;

		$tableName = self::getIndexTableName();
		$charset   = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$tableName} (
			post_id BIGINT UNSIGNED NOT NULL,
			keyword_id BIGINT UNSIGNED NOT NULL,
			link_count INT UNSIGNED NOT NULL DEFAULT 0,
			last_updated DATETIME NOT NULL,
			PRIMARY KEY (post_id, keyword_id),
			KEY keyword_id (keyword_id)
		) {$charset};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		\dbDelta( $sql );

		// Verify table was created.
		return $wpdb->get_var(
			$wpdb->prepare(
				'SHOW TABLES LIKE %s',
				$tableName
			)
		) === $tableName;
	}

	/**
	 * Uninstall the database tables.
	 *
	 * @return bool True on success.
	 */
	public function uninstall(): bool {
		global $wpdb;

		$keywordsTable = self::getKeywordsTableName();
		$indexTable    = self::getIndexTableName();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.SchemaChange -- Uninstall operation.
		$wpdb->query( "DROP TABLE IF EXISTS {$indexTable}" );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.SchemaChange -- Uninstall operation.
		$wpdb->query( "DROP TABLE IF EXISTS {$keywordsTable}" );

		\delete_option( self::VERSION_OPTION );

		return true;
	}

	/**
	 * Check if all tables exist.
	 *
	 * @return bool True if all tables exist.
	 */
	public function tablesExist(): bool {
		global $wpdb;

		$keywordsTable = self::getKeywordsTableName();
		$indexTable    = self::getIndexTableName();

		$keywordsExists = $wpdb->get_var(
			$wpdb->prepare( 'SHOW TABLES LIKE %s', $keywordsTable )
		) === $keywordsTable;

		$indexExists = $wpdb->get_var(
			$wpdb->prepare( 'SHOW TABLES LIKE %s', $indexTable )
		) === $indexTable;

		return $keywordsExists && $indexExists;
	}

	/**
	 * Clear all link index data.
	 *
	 * Useful when rebuilding the index from scratch.
	 *
	 * @return int Number of rows deleted.
	 */
	public function clearIndex(): int {
		global $wpdb;

		$tableName = self::getIndexTableName();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Bulk delete operation.
		$deleted = $wpdb->query( "TRUNCATE TABLE {$tableName}" );

		return $deleted !== false ? $deleted : 0;
	}
}
