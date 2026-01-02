<?php
/**
 * Database Search and Replace Tool
 *
 * Safely performs search and replace operations across the WordPress database,
 * with proper handling of serialized data.
 *
 * @package CrispySEO\Tools
 * @since 2.0.0
 */

declare(strict_types=1);

namespace CrispySEO\Tools;

/**
 * Handles database-wide search and replace operations.
 */
class SearchReplace {

	/**
	 * Serialized data handler.
	 */
	private SerializedHandler $serializedHandler;

	/**
	 * Tables to exclude from search/replace.
	 *
	 * @var array<string>
	 */
	private const EXCLUDED_TABLES = [
		'users',           // Never modify user data automatically.
		'usermeta',        // User metadata protection.
		'signups',         // Multisite signups.
		'registration_log', // Multisite registration.
	];

	/**
	 * Columns to exclude from search/replace.
	 *
	 * @var array<string>
	 */
	private const EXCLUDED_COLUMNS = [
		'user_pass',
		'user_activation_key',
		'session_tokens',
	];

	/**
	 * Batch size for processing.
	 */
	private const BATCH_SIZE = 1000;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->serializedHandler = new SerializedHandler();

		// AJAX handlers.
		add_action( 'wp_ajax_crispy_seo_search_replace', [ $this, 'ajaxSearchReplace' ] );
		add_action( 'wp_ajax_crispy_seo_get_tables', [ $this, 'ajaxGetTables' ] );
	}

	/**
	 * Get list of database tables.
	 *
	 * @param bool $includeExcluded Include normally excluded tables.
	 * @return array<string> Table names.
	 */
	public function getTables( bool $includeExcluded = false ): array {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Schema query.
		$tables = $wpdb->get_col( 'SHOW TABLES' );

		if ( ! $includeExcluded ) {
			$tables = array_filter(
				$tables,
				function ( $table ) use ( $wpdb ) {
					foreach ( self::EXCLUDED_TABLES as $excluded ) {
						if ( $table === $wpdb->prefix . $excluded ) {
							return false;
						}
					}
					return true;
				}
			);
		}

		return array_values( $tables );
	}

	/**
	 * Get list of searchable tables (public alias for CLI).
	 *
	 * @return array<string> Table names.
	 */
	public function getSearchableTables(): array {
		return $this->getTables( false );
	}

	/**
	 * Get columns for a table.
	 *
	 * @param string $table Table name.
	 * @return array<array{name: string, type: string, key: string}> Column info.
	 */
	public function getColumns( string $table ): array {
		global $wpdb;

		// Validate table name.
		$tables = $this->getTables( true );
		if ( ! in_array( $table, $tables, true ) ) {
			return [];
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Schema query with validated table.
		$columns = $wpdb->get_results( "DESCRIBE `{$table}`", ARRAY_A );

		$result = [];
		foreach ( $columns as $column ) {
			$result[] = [
				'name' => $column['Field'],
				'type' => $column['Type'],
				'key'  => $column['Key'],
			];
		}

		return $result;
	}

	/**
	 * Get the primary key column for a table.
	 *
	 * @param string $table Table name.
	 * @return string|null Primary key column name or null.
	 */
	private function getPrimaryKey( string $table ): ?string {
		$columns = $this->getColumns( $table );

		foreach ( $columns as $column ) {
			if ( $column['key'] === 'PRI' ) {
				return $column['name'];
			}
		}

		return null;
	}

	/**
	 * Search for a term across specified tables.
	 *
	 * @param string        $search Search term.
	 * @param array<string> $tables Tables to search (empty = all).
	 * @param int           $limit  Maximum results per table.
	 * @return array<string, array<array<string, mixed>>> Results by table.
	 */
	public function search( string $search, array $tables = [], int $limit = 100 ): array {
		global $wpdb;

		if ( empty( $search ) ) {
			return [];
		}

		if ( empty( $tables ) ) {
			$tables = $this->getTables();
		}

		$results    = [];
		$searchLike = '%' . $wpdb->esc_like( $search ) . '%';

		foreach ( $tables as $table ) {
			$columns    = $this->getColumns( $table );
			$primaryKey = $this->getPrimaryKey( $table );

			if ( empty( $columns ) ) {
				continue;
			}

			// Build search conditions for text columns.
			$conditions = [];
			foreach ( $columns as $column ) {
				if ( $this->isTextColumn( $column['type'] ) && ! in_array( $column['name'], self::EXCLUDED_COLUMNS, true ) ) {
					$conditions[] = "`{$column['name']}` LIKE %s";
				}
			}

			if ( empty( $conditions ) ) {
				continue;
			}

			$whereClause = implode( ' OR ', $conditions );
			$values      = array_fill( 0, count( $conditions ), $searchLike );

			// Build query.
			$selectColumns = $primaryKey ? "`{$primaryKey}`, " : '';
			foreach ( $columns as $column ) {
				if ( $this->isTextColumn( $column['type'] ) ) {
					$selectColumns .= "`{$column['name']}`, ";
				}
			}
			$selectColumns = rtrim( $selectColumns, ', ' );

			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Dynamic query with proper escaping.
			$sql = $wpdb->prepare(
				"SELECT {$selectColumns} FROM `{$table}` WHERE {$whereClause} LIMIT %d",
				array_merge( $values, [ $limit ] )
			);

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Dynamic search query.
			$rows = $wpdb->get_results( $sql, ARRAY_A );

			if ( ! empty( $rows ) ) {
				$results[ $table ] = $rows;
			}
		}

		return $results;
	}

	/**
	 * Perform search and replace operation.
	 *
	 * @param string        $search  Search term.
	 * @param string        $replace Replacement term.
	 * @param array<string> $tables  Tables to process (empty = all).
	 * @param bool          $dryRun  If true, only preview changes.
	 * @return array{affected: int, tables: array<string, int>, errors: array<string>, preview: array<array<string, mixed>>} Results.
	 */
	public function replace( string $search, string $replace, array $tables = [], bool $dryRun = true ): array {
		global $wpdb;

		$result = [
			'affected' => 0,
			'tables'   => [],
			'errors'   => [],
			'preview'  => [],
		];

		if ( empty( $search ) ) {
			$result['errors'][] = __( 'Search term is required.', 'crispy-seo' );
			return $result;
		}

		if ( $search === $replace ) {
			$result['errors'][] = __( 'Search and replace terms are identical.', 'crispy-seo' );
			return $result;
		}

		if ( empty( $tables ) ) {
			$tables = $this->getTables();
		}

		foreach ( $tables as $table ) {
			$tableResult = $this->replaceInTable( $table, $search, $replace, $dryRun );

			$result['affected']        += $tableResult['affected'];
			$result['tables'][ $table ] = $tableResult['affected'];
			$result['errors']           = array_merge( $result['errors'], $tableResult['errors'] );

			if ( $dryRun ) {
				$result['preview'] = array_merge( $result['preview'], $tableResult['preview'] );
			}
		}

		return $result;
	}

	/**
	 * Perform search and replace in a single table.
	 *
	 * Uses database transactions to ensure atomicity - if any update fails,
	 * all changes to this table are rolled back.
	 *
	 * @param string $table   Table name.
	 * @param string $search  Search term.
	 * @param string $replace Replacement term.
	 * @param bool   $dryRun  If true, only preview changes.
	 * @return array{affected: int, errors: array<string>, preview: array<array<string, mixed>>} Results.
	 */
	private function replaceInTable( string $table, string $search, string $replace, bool $dryRun ): array {
		global $wpdb;

		$result = [
			'affected' => 0,
			'errors'   => [],
			'preview'  => [],
		];

		$columns    = $this->getColumns( $table );
		$primaryKey = $this->getPrimaryKey( $table );

		if ( empty( $columns ) || $primaryKey === null ) {
			return $result;
		}

		// Get text columns.
		$textColumns = [];
		foreach ( $columns as $column ) {
			if ( $this->isTextColumn( $column['type'] ) && ! in_array( $column['name'], self::EXCLUDED_COLUMNS, true ) ) {
				$textColumns[] = $column['name'];
			}
		}

		if ( empty( $textColumns ) ) {
			return $result;
		}

		// Build search conditions.
		$searchLike  = '%' . $wpdb->esc_like( $search ) . '%';
		$conditions  = [];
		$values      = [];

		foreach ( $textColumns as $column ) {
			$conditions[] = "`{$column}` LIKE %s";
			$values[]     = $searchLike;
		}

		$whereClause = implode( ' OR ', $conditions );

		// Count total matching rows.
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Dynamic query with proper escaping.
		$countSql = $wpdb->prepare(
			"SELECT COUNT(*) FROM `{$table}` WHERE {$whereClause}",
			$values
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Dynamic count query.
		$totalRows = (int) $wpdb->get_var( $countSql );

		if ( $totalRows === 0 ) {
			return $result;
		}

		// Start transaction for non-dry-run operations.
		if ( ! $dryRun ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Transaction control.
			$wpdb->query( 'START TRANSACTION' );
		}

		$hasError   = false;
		$offset     = 0;
		$columnList = '`' . $primaryKey . '`, `' . implode( '`, `', $textColumns ) . '`';

		try {
			while ( $offset < $totalRows ) {
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Dynamic query with proper escaping.
				$batchSql = $wpdb->prepare(
					"SELECT {$columnList} FROM `{$table}` WHERE {$whereClause} LIMIT %d OFFSET %d",
					array_merge( $values, [ self::BATCH_SIZE, $offset ] )
				);

				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Dynamic batch query.
				$rows = $wpdb->get_results( $batchSql, ARRAY_A );

				if ( empty( $rows ) ) {
					break;
				}

				foreach ( $rows as $row ) {
					$rowId   = $row[ $primaryKey ];
					$updates = [];

					foreach ( $textColumns as $column ) {
						if ( ! isset( $row[ $column ] ) || ! str_contains( (string) $row[ $column ], $search ) ) {
							continue;
						}

						$originalValue = $row[ $column ];
						$newValue      = $this->replaceValue( $originalValue, $search, $replace );

						if ( $newValue !== false && $newValue !== $originalValue ) {
							$updates[ $column ] = $newValue;

							if ( $dryRun && count( $result['preview'] ) < 50 ) {
								$result['preview'][] = [
									'table'    => $table,
									'row_id'   => $rowId,
									'column'   => $column,
									'original' => $this->truncateForPreview( $originalValue ),
									'new'      => $this->truncateForPreview( $newValue ),
								];
							}
						}
					}

					if ( ! empty( $updates ) ) {
						++$result['affected'];

						if ( ! $dryRun ) {
							// Perform the update.
							// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Update operation.
							$updated = $wpdb->update(
								$table,
								$updates,
								[ $primaryKey => $rowId ],
								array_fill( 0, count( $updates ), '%s' ),
								[ is_numeric( $rowId ) ? '%d' : '%s' ]
							);

							if ( $updated === false ) {
								$hasError           = true;
								$result['errors'][] = sprintf(
									/* translators: 1: table name, 2: row ID */
									__( 'Failed to update %1$s row %2$s', 'crispy-seo' ),
									$table,
									$rowId
								);
								// Break on first error to trigger rollback.
								break 2;
							}
						}
					}
				}

				$offset += self::BATCH_SIZE;
			}
		} catch ( \Exception $e ) {
			$hasError           = true;
			$result['errors'][] = sprintf(
				/* translators: 1: table name, 2: error message */
				__( 'Exception in table %1$s: %2$s', 'crispy-seo' ),
				$table,
				$e->getMessage()
			);
		}

		// Commit or rollback transaction.
		if ( ! $dryRun ) {
			if ( $hasError ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Transaction control.
				$wpdb->query( 'ROLLBACK' );
				$result['affected'] = 0;
				$result['errors'][] = sprintf(
					/* translators: %s: table name */
					__( 'All changes to %s have been rolled back due to errors.', 'crispy-seo' ),
					$table
				);
			} else {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Transaction control.
				$wpdb->query( 'COMMIT' );
			}
		}

		return $result;
	}

	/**
	 * Replace a value, handling serialized data.
	 *
	 * @param string $value   Original value.
	 * @param string $search  Search term.
	 * @param string $replace Replacement term.
	 * @return string|false Modified value or false on failure.
	 */
	private function replaceValue( string $value, string $search, string $replace ): string|false {
		// Check if value contains serialized data.
		if ( $this->serializedHandler->containsSerialized( $value ) ) {
			return $this->serializedHandler->replaceInSerialized( $value, $search, $replace );
		}

		// Simple string replacement.
		return str_replace( $search, $replace, $value );
	}

	/**
	 * Check if a column type is a text type.
	 *
	 * @param string $type Column type.
	 * @return bool True if text type.
	 */
	private function isTextColumn( string $type ): bool {
		$textTypes = [
			'char',
			'varchar',
			'text',
			'tinytext',
			'mediumtext',
			'longtext',
			'blob',
			'tinyblob',
			'mediumblob',
			'longblob',
		];

		$lowerType = strtolower( $type );

		foreach ( $textTypes as $textType ) {
			if ( str_contains( $lowerType, $textType ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Truncate a value for preview display.
	 *
	 * @param string $value Value to truncate.
	 * @param int    $maxLength Maximum length.
	 * @return string Truncated value.
	 */
	private function truncateForPreview( string $value, int $maxLength = 200 ): string {
		if ( strlen( $value ) <= $maxLength ) {
			return $value;
		}

		return substr( $value, 0, $maxLength ) . '...';
	}

	/**
	 * AJAX handler: Perform search/replace.
	 */
	public function ajaxSearchReplace(): void {
		check_ajax_referer( 'crispy_seo_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'Permission denied.', 'crispy-seo' ) ] );
		}

		$search  = isset( $_POST['search'] ) ? sanitize_text_field( wp_unslash( $_POST['search'] ) ) : '';
		$replace = isset( $_POST['replace'] ) ? sanitize_text_field( wp_unslash( $_POST['replace'] ) ) : '';
		$tables  = isset( $_POST['tables'] ) ? array_map( 'sanitize_text_field', (array) $_POST['tables'] ) : [];
		$dryRun  = ! isset( $_POST['confirm'] ) || $_POST['confirm'] !== 'true';

		if ( empty( $search ) ) {
			wp_send_json_error( [ 'message' => __( 'Search term is required.', 'crispy-seo' ) ] );
		}

		// Validate tables against allowed list.
		$allowedTables = $this->getTables();
		$tables        = array_intersect( $tables, $allowedTables );

		$result = $this->replace( $search, $replace, $tables, $dryRun );

		if ( ! empty( $result['errors'] ) && $result['affected'] === 0 ) {
			wp_send_json_error(
				[
					'message' => implode( "\n", $result['errors'] ),
					'result'  => $result,
				]
			);
		}

		$message = $dryRun
			? sprintf(
				/* translators: %d: number of rows */
				__( 'Preview: %d rows would be affected.', 'crispy-seo' ),
				$result['affected']
			)
			: sprintf(
				/* translators: %d: number of rows */
				__( 'Successfully updated %d rows.', 'crispy-seo' ),
				$result['affected']
			);

		wp_send_json_success(
			[
				'message' => $message,
				'result'  => $result,
				'dry_run' => $dryRun,
			]
		);
	}

	/**
	 * AJAX handler: Get available tables.
	 */
	public function ajaxGetTables(): void {
		check_ajax_referer( 'crispy_seo_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'Permission denied.', 'crispy-seo' ) ] );
		}

		$tables = $this->getTables();

		wp_send_json_success( [ 'tables' => $tables ] );
	}
}
