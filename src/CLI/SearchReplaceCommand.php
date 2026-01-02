<?php
/**
 * Search & Replace WP-CLI Command
 *
 * Provides CLI commands for database search and replace.
 *
 * @package CrispySEO\CLI
 * @since 2.0.0
 */

declare(strict_types=1);

namespace CrispySEO\CLI;

use CrispySEO\Tools\SearchReplace;
use WP_CLI;

/**
 * Search and replace strings in the WordPress database.
 */
class SearchReplaceCommand {

	/**
	 * Search replace instance.
	 *
	 * @var SearchReplace
	 */
	private SearchReplace $tool;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->tool = new SearchReplace();
	}

	/**
	 * Search for a string in the database.
	 *
	 * ## OPTIONS
	 *
	 * <search>
	 * : String to search for.
	 *
	 * [--tables=<tables>]
	 * : Comma-separated list of tables to search. Default: all.
	 *
	 * [--format=<format>]
	 * : Output format.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - json
	 *   - count
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     wp crispy-seo search-replace search "http://oldsite.com"
	 *     wp crispy-seo search-replace search "old-text" --tables=wp_posts,wp_postmeta
	 *     wp crispy-seo search-replace search "pattern" --format=count
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assocArgs  Associative arguments.
	 */
	public function search( array $args, array $assocArgs ): void {
		$search = $args[0];
		$format = $assocArgs['format'] ?? 'table';
		$tables = isset( $assocArgs['tables'] ) ? explode( ',', $assocArgs['tables'] ) : [];

		WP_CLI::log( sprintf( 'Searching for: %s', $search ) );

		$resultsByTable = $this->tool->search( $search, $tables );

		// Process results: search() returns array<string, array<array<string, mixed>>> (by table).
		$totalMatches   = 0;
		$tablesAffected = 0;
		$preview        = [];

		foreach ( $resultsByTable as $tableName => $rows ) {
			if ( ! empty( $rows ) ) {
				++$tablesAffected;
				$totalMatches += count( $rows );

				// Build preview items from each row.
				foreach ( $rows as $row ) {
					// Find the primary key column (usually 'id' or 'ID').
					$rowId = $row['id'] ?? $row['ID'] ?? array_values( $row )[0] ?? 0;

					// Find which column(s) contain the match.
					foreach ( $row as $column => $value ) {
						if ( is_string( $value ) && stripos( $value, $search ) !== false ) {
							$preview[] = [
								'table'     => $tableName,
								'column'    => $column,
								'row_id'    => $rowId,
								'old_value' => $value,
							];
						}
					}
				}
			}
		}

		if ( $format === 'count' ) {
			WP_CLI::log( sprintf( 'Found %d occurrences.', $totalMatches ) );
			return;
		}

		if ( $totalMatches === 0 ) {
			WP_CLI::log( 'No matches found.' );
			return;
		}

		WP_CLI::log( sprintf( 'Found %d occurrences in %d tables.', $totalMatches, $tablesAffected ) );

		if ( $format === 'json' ) {
			WP_CLI::log( \wp_json_encode( $preview, JSON_PRETTY_PRINT ) );
			return;
		}

		// Table format.
		$items = array_map(
			function ( $item ) {
				$value = $item['old_value'];
				return [
					'table'  => $item['table'],
					'column' => $item['column'],
					'row_id' => $item['row_id'],
					'value'  => substr( $value, 0, 50 ) . ( strlen( $value ) > 50 ? '...' : '' ),
				];
			},
			array_slice( $preview, 0, 50 )
		);

		WP_CLI\Utils\format_items( 'table', $items, [ 'table', 'column', 'row_id', 'value' ] );

		if ( count( $preview ) > 50 ) {
			WP_CLI::log( sprintf( '... and %d more matches.', count( $preview ) - 50 ) );
		}
	}

	/**
	 * Replace a string in the database.
	 *
	 * ## OPTIONS
	 *
	 * <search>
	 * : String to search for.
	 *
	 * <replace>
	 * : Replacement string.
	 *
	 * [--tables=<tables>]
	 * : Comma-separated list of tables to process. Default: all safe tables.
	 *
	 * [--dry-run]
	 * : Preview changes without applying.
	 *
	 * [--yes]
	 * : Skip confirmation.
	 *
	 * ## EXAMPLES
	 *
	 *     wp crispy-seo search-replace replace "http://old.com" "https://new.com" --dry-run
	 *     wp crispy-seo search-replace replace "old" "new" --yes
	 *     wp crispy-seo search-replace replace "foo" "bar" --tables=wp_posts
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assocArgs  Associative arguments.
	 */
	public function replace( array $args, array $assocArgs ): void {
		$search  = $args[0];
		$replace = $args[1];
		$tables  = isset( $assocArgs['tables'] ) ? explode( ',', $assocArgs['tables'] ) : [];
		$dryRun  = isset( $assocArgs['dry-run'] );

		WP_CLI::log( sprintf( 'Search: %s', $search ) );
		WP_CLI::log( sprintf( 'Replace: %s', $replace ) );

		if ( ! $dryRun && ! isset( $assocArgs['yes'] ) ) {
			WP_CLI::confirm( 'This will modify your database. Proceed?' );
		}

		$progress = null;

		if ( ! $dryRun ) {
			// Create progress bar.
			$allTables = empty( $tables ) ? $this->tool->getSearchableTables() : $tables;
			$progress  = WP_CLI\Utils\make_progress_bar( 'Processing tables', count( $allTables ) );
		}

		$result = $this->tool->replace( $search, $replace, $tables, $dryRun );

		if ( $progress ) {
			$progress->finish();
		}

		// Extract counts from result - replace() returns 'affected' and 'tables' keys.
		$affectedRows   = $result['affected'] ?? 0;
		$tablesAffected = is_array( $result['tables'] ?? null ) ? count( $result['tables'] ) : 0;
		$preview        = $result['preview'] ?? [];
		$errors         = $result['errors'] ?? [];

		if ( $dryRun ) {
			WP_CLI::log( sprintf( 'Dry run: Would replace %d occurrences in %d tables.', $affectedRows, $tablesAffected ) );

			if ( ! empty( $preview ) ) {
				WP_CLI::log( '' );
				WP_CLI::log( 'Preview (first 20):' );

				$items = array_map(
					function ( $item ) {
						return [
							'table'     => $item['table'] ?? '',
							'column'    => $item['column'] ?? '',
							'row_id'    => $item['row_id'] ?? 0,
							'old_value' => substr( $item['old_value'] ?? '', 0, 40 ),
							'new_value' => substr( $item['new_value'] ?? '', 0, 40 ),
						];
					},
					array_slice( $preview, 0, 20 )
				);

				WP_CLI\Utils\format_items( 'table', $items, [ 'table', 'column', 'row_id', 'old_value', 'new_value' ] );
			}
		} else {
			WP_CLI::success( sprintf( 'Replaced %d occurrences.', $affectedRows ) );
		}

		if ( ! empty( $errors ) ) {
			foreach ( $errors as $error ) {
				WP_CLI::warning( $error );
			}
		}
	}

	/**
	 * List searchable tables.
	 *
	 * ## EXAMPLES
	 *
	 *     wp crispy-seo search-replace tables
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assocArgs  Associative arguments.
	 */
	public function tables( array $args, array $assocArgs ): void {
		$tables = $this->tool->getSearchableTables();

		WP_CLI::log( 'Searchable tables:' );
		foreach ( $tables as $table ) {
			WP_CLI::log( '  ' . $table );
		}

		WP_CLI::log( '' );
		WP_CLI::log( sprintf( 'Total: %d tables', count( $tables ) ) );
	}
}
