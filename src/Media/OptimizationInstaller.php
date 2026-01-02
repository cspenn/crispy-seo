<?php
/**
 * Image optimization queue database table installer.
 *
 * @package CrispySEO
 * @since 2.0.0
 */

declare(strict_types=1);

namespace CrispySEO\Media;

/**
 * Handles creation and updates of the optimization queue database table.
 */
class OptimizationInstaller {

	/**
	 * Database version for tracking schema updates.
	 */
	private const DB_VERSION = '1.0.0';

	/**
	 * Option key for stored database version.
	 */
	private const VERSION_OPTION = 'crispy_seo_optimization_db_version';

	/**
	 * Get the table name with WordPress prefix.
	 *
	 * @return string Table name.
	 */
	public static function getTableName(): string {
		global $wpdb;
		return $wpdb->prefix . 'crispy_seo_optimization_queue';
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
			$this->ensureBackupDirectory();
		}

		return $result;
	}

	/**
	 * Create the optimization queue table.
	 *
	 * @return bool True on success.
	 */
	private function createTable(): bool {
		global $wpdb;

		$tableName = self::getTableName();
		$charset   = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$tableName} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			attachment_id BIGINT UNSIGNED NOT NULL,
			status ENUM('pending', 'processing', 'completed', 'failed', 'skipped') NOT NULL DEFAULT 'pending',
			optimization_type ENUM('compress', 'webp', 'both') NOT NULL DEFAULT 'both',
			original_size BIGINT UNSIGNED NOT NULL DEFAULT 0,
			optimized_size BIGINT UNSIGNED NULL,
			savings_percent DECIMAL(5,2) NULL,
			webp_size BIGINT UNSIGNED NULL,
			backup_path VARCHAR(500) NULL,
			error_message TEXT NULL,
			attempts INT UNSIGNED NOT NULL DEFAULT 0,
			created_at DATETIME NOT NULL,
			processed_at DATETIME NULL,
			PRIMARY KEY (id),
			KEY attachment_id (attachment_id),
			KEY status (status),
			KEY optimization_type (optimization_type),
			KEY created_at (created_at)
		) {$charset};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		// Verify table was created.
		return $wpdb->get_var(
			$wpdb->prepare(
				'SHOW TABLES LIKE %s',
				$tableName
			)
		) === $tableName;
	}

	/**
	 * Ensure the backup directory exists.
	 *
	 * @return bool True if directory exists or was created.
	 */
	private function ensureBackupDirectory(): bool {
		$backupDir = self::getBackupDirectory();

		if ( ! file_exists( $backupDir ) ) {
			$created = wp_mkdir_p( $backupDir );

			if ( $created ) {
				// Add .htaccess to prevent direct access.
				$htaccessContent = "Order deny,allow\nDeny from all";
				file_put_contents( $backupDir . '/.htaccess', $htaccessContent );

				// Add index.php for extra protection.
				file_put_contents( $backupDir . '/index.php', '<?php // Silence is golden.' );
			}

			return $created;
		}

		return true;
	}

	/**
	 * Get the backup directory path.
	 *
	 * @return string Backup directory path.
	 */
	public static function getBackupDirectory(): string {
		$uploadDir = wp_upload_dir();
		return trailingslashit( $uploadDir['basedir'] ) . 'crispy-seo-backups';
	}

	/**
	 * Uninstall the database table.
	 *
	 * @param bool $deleteBackups Whether to delete backup files.
	 * @return bool True on success.
	 */
	public function uninstall( bool $deleteBackups = false ): bool {
		global $wpdb;

		$tableName = self::getTableName();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.SchemaChange -- Uninstall operation.
		$wpdb->query( "DROP TABLE IF EXISTS {$tableName}" );

		delete_option( self::VERSION_OPTION );

		// Optionally delete backup files.
		if ( $deleteBackups ) {
			$this->deleteBackupDirectory();
		}

		return true;
	}

	/**
	 * Delete the backup directory and all its contents.
	 *
	 * @return bool True on success.
	 */
	private function deleteBackupDirectory(): bool {
		$backupDir = self::getBackupDirectory();

		if ( ! file_exists( $backupDir ) ) {
			return true;
		}

		// Use WordPress filesystem API.
		require_once ABSPATH . 'wp-admin/includes/file.php';
		WP_Filesystem();
		global $wp_filesystem;

		return $wp_filesystem->rmdir( $backupDir, true );
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

	/**
	 * Get queue statistics.
	 *
	 * @return array<string, int> Statistics by status.
	 */
	public function getStats(): array {
		global $wpdb;

		$tableName = self::getTableName();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Statistics query.
		$results = $wpdb->get_results(
			"SELECT status, COUNT(*) as count FROM {$tableName} GROUP BY status",
			ARRAY_A
		);

		$stats = [
			'pending'    => 0,
			'processing' => 0,
			'completed'  => 0,
			'failed'     => 0,
			'skipped'    => 0,
			'total'      => 0,
		];

		foreach ( $results as $row ) {
			$stats[ $row['status'] ] = (int) $row['count'];
			$stats['total']         += (int) $row['count'];
		}

		return $stats;
	}

	/**
	 * Get total savings from optimizations.
	 *
	 * @return array{original: int, optimized: int, savings: int, percent: float} Savings data.
	 */
	public function getTotalSavings(): array {
		global $wpdb;

		$tableName = self::getTableName();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Statistics query.
		$result = $wpdb->get_row(
			"SELECT
				SUM(original_size) as original,
				SUM(optimized_size) as optimized
			FROM {$tableName}
			WHERE status = 'completed' AND optimized_size IS NOT NULL",
			ARRAY_A
		);

		$original  = (int) ( $result['original'] ?? 0 );
		$optimized = (int) ( $result['optimized'] ?? 0 );
		$savings   = $original - $optimized;
		$percent   = $original > 0 ? round( ( $savings / $original ) * 100, 2 ) : 0.0;

		return [
			'original'  => $original,
			'optimized' => $optimized,
			'savings'   => $savings,
			'percent'   => $percent,
		];
	}

	/**
	 * Clear failed items from the queue.
	 *
	 * @return int Number of rows deleted.
	 */
	public function clearFailed(): int {
		global $wpdb;

		$tableName = self::getTableName();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Cleanup operation.
		$deleted = $wpdb->delete(
			$tableName,
			[ 'status' => 'failed' ],
			[ '%s' ]
		);

		return $deleted !== false ? $deleted : 0;
	}

	/**
	 * Reset processing items back to pending.
	 *
	 * Useful for recovering from interrupted operations.
	 *
	 * @return int Number of rows updated.
	 */
	public function resetProcessing(): int {
		global $wpdb;

		$tableName = self::getTableName();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Recovery operation.
		$updated = $wpdb->update(
			$tableName,
			[ 'status' => 'pending' ],
			[ 'status' => 'processing' ],
			[ '%s' ],
			[ '%s' ]
		);

		return $updated !== false ? $updated : 0;
	}
}
