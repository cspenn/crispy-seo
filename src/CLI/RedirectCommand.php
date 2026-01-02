<?php
/**
 * Redirect WP-CLI Command
 *
 * Provides CLI commands for managing redirects.
 *
 * @package CrispySEO\CLI
 * @since 2.0.0
 */

declare(strict_types=1);

namespace CrispySEO\CLI;

use CrispySEO\Technical\RedirectManager;
use WP_CLI;

/**
 * Manage redirects in Crispy SEO.
 */
class RedirectCommand {

	/**
	 * Redirect manager instance.
	 *
	 * @var RedirectManager
	 */
	private RedirectManager $manager;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->manager = new RedirectManager();
	}

	/**
	 * List all redirects.
	 *
	 * ## OPTIONS
	 *
	 * [--format=<format>]
	 * : Output format.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - csv
	 *   - json
	 * ---
	 *
	 * [--status=<status>]
	 * : Filter by enabled status.
	 * ---
	 * options:
	 *   - enabled
	 *   - disabled
	 *   - all
	 * default: all
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     wp crispy-seo redirect list
	 *     wp crispy-seo redirect list --format=csv
	 *     wp crispy-seo redirect list --status=enabled
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assocArgs  Associative arguments.
	 */
	public function list( array $args, array $assocArgs ): void {
		global $wpdb;

		$format = $assocArgs['format'] ?? 'table';
		$status = $assocArgs['status'] ?? 'all';

		$tableName = $wpdb->prefix . 'crispy_seo_redirects';

		$where = '';
		if ( $status === 'enabled' ) {
			$where = 'WHERE enabled = 1';
		} elseif ( $status === 'disabled' ) {
			$where = 'WHERE enabled = 0';
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$redirects = $wpdb->get_results(
			"SELECT id, source_path, target_url, redirect_type, match_type, hit_count, enabled FROM {$tableName} {$where} ORDER BY id ASC",
			ARRAY_A
		);

		if ( empty( $redirects ) ) {
			WP_CLI::warning( 'No redirects found.' );
			return;
		}

		WP_CLI\Utils\format_items( $format, $redirects, [ 'id', 'source_path', 'target_url', 'redirect_type', 'match_type', 'hit_count', 'enabled' ] );
	}

	/**
	 * Add a new redirect.
	 *
	 * ## OPTIONS
	 *
	 * <source>
	 * : Source path to redirect from.
	 *
	 * <target>
	 * : Target URL to redirect to.
	 *
	 * [--type=<type>]
	 * : Redirect status code.
	 * ---
	 * default: 301
	 * options:
	 *   - 301
	 *   - 302
	 *   - 307
	 *   - 308
	 *   - 410
	 *   - 451
	 * ---
	 *
	 * [--match=<match>]
	 * : Match type.
	 * ---
	 * default: exact
	 * options:
	 *   - exact
	 *   - wildcard
	 *   - regex
	 * ---
	 *
	 * [--notes=<notes>]
	 * : Optional notes.
	 *
	 * ## EXAMPLES
	 *
	 *     wp crispy-seo redirect add /old-page /new-page
	 *     wp crispy-seo redirect add /old/* /new/* --match=wildcard
	 *     wp crispy-seo redirect add /gone-page "" --type=410
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assocArgs  Associative arguments.
	 */
	public function add( array $args, array $assocArgs ): void {
		$source    = $args[0];
		$target    = $args[1] ?? '';
		$type      = (int) ( $assocArgs['type'] ?? 301 );
		$matchType = $assocArgs['match'] ?? 'exact';
		$notes     = $assocArgs['notes'] ?? '';

		$result = $this->manager->addRedirect( $source, $target, $type, $matchType, $notes );

		if ( $result ) {
			WP_CLI::success( sprintf( 'Redirect added with ID %d.', $result ) );
		} else {
			WP_CLI::error( 'Failed to add redirect. Source may already exist.' );
		}
	}

	/**
	 * Delete a redirect.
	 *
	 * ## OPTIONS
	 *
	 * <id>
	 * : Redirect ID to delete.
	 *
	 * [--yes]
	 * : Skip confirmation.
	 *
	 * ## EXAMPLES
	 *
	 *     wp crispy-seo redirect delete 123
	 *     wp crispy-seo redirect delete 123 --yes
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assocArgs  Associative arguments.
	 */
	public function delete( array $args, array $assocArgs ): void {
		$id = (int) $args[0];

		if ( ! isset( $assocArgs['yes'] ) ) {
			WP_CLI::confirm( sprintf( 'Delete redirect ID %d?', $id ) );
		}

		if ( $this->manager->deleteRedirect( $id ) ) {
			WP_CLI::success( 'Redirect deleted.' );
		} else {
			WP_CLI::error( 'Failed to delete redirect.' );
		}
	}

	/**
	 * Import redirects from CSV.
	 *
	 * ## OPTIONS
	 *
	 * <file>
	 * : Path to CSV file.
	 *
	 * [--dry-run]
	 * : Preview import without making changes.
	 *
	 * ## EXAMPLES
	 *
	 *     wp crispy-seo redirect import /path/to/redirects.csv
	 *     wp crispy-seo redirect import redirects.csv --dry-run
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assocArgs  Associative arguments.
	 */
	public function import( array $args, array $assocArgs ): void {
		$file   = $args[0];
		$dryRun = isset( $assocArgs['dry-run'] );

		if ( ! file_exists( $file ) ) {
			WP_CLI::error( 'File not found: ' . $file );
		}

		$csv = file_get_contents( $file );

		if ( $csv === false ) {
			WP_CLI::error( 'Failed to read file.' );
		}

		$result = $this->manager->importFromCsv( $csv, $dryRun );

		if ( $dryRun ) {
			WP_CLI::log( sprintf( 'Dry run: Would import %d redirects, skip %d.', $result['imported'], $result['skipped'] ) );
		} else {
			WP_CLI::success( sprintf( 'Imported %d redirects, skipped %d.', $result['imported'], $result['skipped'] ) );
		}

		if ( ! empty( $result['errors'] ) ) {
			foreach ( $result['errors'] as $error ) {
				WP_CLI::warning( $error );
			}
		}
	}

	/**
	 * Export redirects to CSV.
	 *
	 * ## OPTIONS
	 *
	 * [--file=<file>]
	 * : Output file path. If not specified, outputs to stdout.
	 *
	 * ## EXAMPLES
	 *
	 *     wp crispy-seo redirect export
	 *     wp crispy-seo redirect export --file=/path/to/redirects.csv
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assocArgs  Associative arguments.
	 */
	public function export( array $args, array $assocArgs ): void {
		$file = $assocArgs['file'] ?? '';

		$csv = $this->manager->exportToCsv();

		if ( $file ) {
			if ( file_put_contents( $file, $csv ) !== false ) {
				WP_CLI::success( 'Exported to ' . $file );
			} else {
				WP_CLI::error( 'Failed to write file.' );
			}
		} else {
			echo $csv;
		}
	}

	/**
	 * Show redirect statistics.
	 *
	 * ## EXAMPLES
	 *
	 *     wp crispy-seo redirect stats
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assocArgs  Associative arguments.
	 */
	public function stats( array $args, array $assocArgs ): void {
		global $wpdb;

		$tableName = $wpdb->prefix . 'crispy_seo_redirects';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$stats = $wpdb->get_row(
			"SELECT
				COUNT(*) as total,
				SUM(CASE WHEN enabled = 1 THEN 1 ELSE 0 END) as enabled,
				SUM(CASE WHEN enabled = 0 THEN 1 ELSE 0 END) as disabled,
				SUM(hit_count) as total_hits,
				SUM(CASE WHEN redirect_type = 301 THEN 1 ELSE 0 END) as type_301,
				SUM(CASE WHEN redirect_type = 302 THEN 1 ELSE 0 END) as type_302,
				SUM(CASE WHEN redirect_type = 410 THEN 1 ELSE 0 END) as type_410
			FROM {$tableName}",
			ARRAY_A
		);

		WP_CLI::log( 'Redirect Statistics:' );
		WP_CLI::log( sprintf( '  Total redirects: %d', $stats['total'] ?? 0 ) );
		WP_CLI::log( sprintf( '  Enabled: %d', $stats['enabled'] ?? 0 ) );
		WP_CLI::log( sprintf( '  Disabled: %d', $stats['disabled'] ?? 0 ) );
		WP_CLI::log( sprintf( '  Total hits: %d', $stats['total_hits'] ?? 0 ) );
		WP_CLI::log( 'By Type:' );
		WP_CLI::log( sprintf( '  301 (Permanent): %d', $stats['type_301'] ?? 0 ) );
		WP_CLI::log( sprintf( '  302 (Temporary): %d', $stats['type_302'] ?? 0 ) );
		WP_CLI::log( sprintf( '  410 (Gone): %d', $stats['type_410'] ?? 0 ) );
	}

	/**
	 * Migrate redirects from Rank Math.
	 *
	 * ## OPTIONS
	 *
	 * [--dry-run]
	 * : Preview migration without making changes.
	 *
	 * ## EXAMPLES
	 *
	 *     wp crispy-seo redirect migrate-rankmath
	 *     wp crispy-seo redirect migrate-rankmath --dry-run
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assocArgs  Associative arguments.
	 */
	public function migrate_rankmath( array $args, array $assocArgs ): void {
		$dryRun = isset( $assocArgs['dry-run'] );

		$migration = new \CrispySEO\Migrations\RankMathRedirects();

		if ( ! $migration->hasData() ) {
			WP_CLI::error( 'No Rank Math redirects found.' );
		}

		$count = $migration->getCount();
		WP_CLI::log( sprintf( 'Found %d Rank Math redirects.', $count ) );

		if ( $dryRun ) {
			$result = $migration->migrate( true );
			WP_CLI::log( sprintf( 'Dry run: Would import %d, skip %d.', $result['imported'], $result['skipped'] ) );
		} else {
			WP_CLI::confirm( 'Proceed with migration?' );
			$result = $migration->migrate( false );
			WP_CLI::success( sprintf( 'Migrated %d redirects, skipped %d.', $result['imported'], $result['skipped'] ) );
		}

		if ( ! empty( $result['errors'] ) ) {
			foreach ( array_slice( $result['errors'], 0, 10 ) as $error ) {
				WP_CLI::warning( $error );
			}
			if ( count( $result['errors'] ) > 10 ) {
				WP_CLI::warning( sprintf( '... and %d more errors.', count( $result['errors'] ) - 10 ) );
			}
		}
	}
}
