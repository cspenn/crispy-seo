<?php
/**
 * Internal Links WP-CLI Command
 *
 * Provides CLI commands for managing internal links.
 *
 * @package CrispySEO\CLI
 * @since 2.0.0
 */

declare(strict_types=1);

namespace CrispySEO\CLI;

use CrispySEO\Content\InternalLinkManager;
use WP_CLI;

/**
 * Manage internal link keywords in Crispy SEO.
 */
class LinkCommand {

	/**
	 * Internal link manager instance.
	 *
	 * @var InternalLinkManager
	 */
	private InternalLinkManager $manager;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->manager = new InternalLinkManager();
	}

	/**
	 * List all keywords.
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
	 * : Filter by status.
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
	 *     wp crispy-seo links list
	 *     wp crispy-seo links list --format=json
	 *     wp crispy-seo links list --status=enabled
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assocArgs  Associative arguments.
	 */
	public function list( array $args, array $assocArgs ): void {
		$format = $assocArgs['format'] ?? 'table';
		$status = $assocArgs['status'] ?? 'all';

		$result   = $this->manager->getKeywords( 1000, 0 );
		$keywords = $result['keywords'];

		// Filter by status.
		if ( $status !== 'all' ) {
			$keywords = array_filter(
				$keywords,
				function ( $kw ) use ( $status ) {
					return ( $status === 'enabled' ) ? (bool) $kw['enabled'] : ! $kw['enabled'];
				}
			);
		}

		if ( empty( $keywords ) ) {
			WP_CLI::warning( 'No keywords found.' );
			return;
		}

		$items = array_map(
			function ( $kw ) {
				return [
					'id'         => $kw['id'],
					'keyword'    => $kw['keyword'],
					'post_id'    => $kw['target_post_id'],
					'post_title' => $kw['post_title'],
					'max_links'  => $kw['max_links_per_page'],
					'priority'   => $kw['priority'],
					'enabled'    => $kw['enabled'] ? 'yes' : 'no',
				];
			},
			$keywords
		);

		WP_CLI\Utils\format_items( $format, $items, [ 'id', 'keyword', 'post_id', 'post_title', 'max_links', 'priority', 'enabled' ] );
	}

	/**
	 * Add a new keyword.
	 *
	 * ## OPTIONS
	 *
	 * <keyword>
	 * : Keyword or phrase to link.
	 *
	 * <post_id>
	 * : Target post ID.
	 *
	 * [--anchor=<anchor>]
	 * : Custom anchor text. Defaults to keyword.
	 *
	 * [--max-links=<max>]
	 * : Maximum links per post.
	 * ---
	 * default: 3
	 * ---
	 *
	 * [--priority=<priority>]
	 * : Priority (higher = linked first).
	 * ---
	 * default: 10
	 * ---
	 *
	 * [--case-sensitive]
	 * : Enable case-sensitive matching.
	 *
	 * ## EXAMPLES
	 *
	 *     wp crispy-seo links add "artificial intelligence" 123
	 *     wp crispy-seo links add "AI" 123 --anchor="artificial intelligence" --priority=20
	 *     wp crispy-seo links add "Machine Learning" 456 --case-sensitive
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assocArgs  Associative arguments.
	 */
	public function add( array $args, array $assocArgs ): void {
		$keyword       = $args[0];
		$postId        = (int) $args[1];
		$anchorText    = $assocArgs['anchor'] ?? '';
		$maxLinks      = (int) ( $assocArgs['max-links'] ?? 3 );
		$priority      = (int) ( $assocArgs['priority'] ?? 10 );
		$caseSensitive = isset( $assocArgs['case-sensitive'] );

		// Validate post exists.
		$post = get_post( $postId );
		if ( ! $post ) {
			WP_CLI::error( sprintf( 'Post ID %d not found.', $postId ) );
		}

		$result = $this->manager->addKeyword( $keyword, $postId, $anchorText, $maxLinks, $caseSensitive, $priority );

		if ( $result ) {
			WP_CLI::success( sprintf( 'Keyword added with ID %d. Target: %s', $result, $post->post_title ) );
		} else {
			WP_CLI::error( 'Failed to add keyword. It may already exist.' );
		}
	}

	/**
	 * Delete a keyword.
	 *
	 * ## OPTIONS
	 *
	 * <id>
	 * : Keyword ID to delete.
	 *
	 * [--yes]
	 * : Skip confirmation.
	 *
	 * ## EXAMPLES
	 *
	 *     wp crispy-seo links delete 123
	 *     wp crispy-seo links delete 123 --yes
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assocArgs  Associative arguments.
	 */
	public function delete( array $args, array $assocArgs ): void {
		$id = (int) $args[0];

		if ( ! isset( $assocArgs['yes'] ) ) {
			WP_CLI::confirm( sprintf( 'Delete keyword ID %d?', $id ) );
		}

		if ( $this->manager->deleteKeyword( $id ) ) {
			WP_CLI::success( 'Keyword deleted.' );
		} else {
			WP_CLI::error( 'Failed to delete keyword.' );
		}
	}

	/**
	 * Rebuild the link index.
	 *
	 * Scans all posts and indexes keyword occurrences for faster linking.
	 *
	 * ## EXAMPLES
	 *
	 *     wp crispy-seo links rebuild
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assocArgs  Associative arguments.
	 */
	public function rebuild( array $args, array $assocArgs ): void {
		WP_CLI::log( 'Rebuilding link index...' );

		$this->manager->rebuildIndex();

		$stats = $this->manager->getStats();

		WP_CLI::success(
			sprintf(
				'Index rebuilt. %d keywords, %d posts indexed.',
				$stats['enabled_keywords'],
				$stats['indexed_posts']
			)
		);
	}

	/**
	 * Show link statistics.
	 *
	 * ## EXAMPLES
	 *
	 *     wp crispy-seo links stats
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assocArgs  Associative arguments.
	 */
	public function stats( array $args, array $assocArgs ): void {
		$stats = $this->manager->getStats();

		WP_CLI::log( 'Internal Links Statistics:' );
		WP_CLI::log( sprintf( '  Total keywords: %d', $stats['total_keywords'] ) );
		WP_CLI::log( sprintf( '  Enabled keywords: %d', $stats['enabled_keywords'] ) );
		WP_CLI::log( sprintf( '  Indexed posts: %d', $stats['indexed_posts'] ) );
	}

	/**
	 * Enable or disable a keyword.
	 *
	 * ## OPTIONS
	 *
	 * <id>
	 * : Keyword ID.
	 *
	 * <status>
	 * : New status.
	 * ---
	 * options:
	 *   - enable
	 *   - disable
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     wp crispy-seo links toggle 123 enable
	 *     wp crispy-seo links toggle 123 disable
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assocArgs  Associative arguments.
	 */
	public function toggle( array $args, array $assocArgs ): void {
		$id      = (int) $args[0];
		$status  = $args[1];
		$enabled = $status === 'enable' ? 1 : 0;

		if ( $this->manager->updateKeyword( $id, [ 'enabled' => $enabled ] ) ) {
			WP_CLI::success( sprintf( 'Keyword %s.', $status === 'enable' ? 'enabled' : 'disabled' ) );
		} else {
			WP_CLI::error( 'Failed to update keyword.' );
		}
	}

	/**
	 * Migrate keywords from Internal Link Juicer.
	 *
	 * ## OPTIONS
	 *
	 * [--dry-run]
	 * : Preview migration without making changes.
	 *
	 * ## EXAMPLES
	 *
	 *     wp crispy-seo links migrate-ilj
	 *     wp crispy-seo links migrate-ilj --dry-run
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assocArgs  Associative arguments.
	 */
	public function migrate_ilj( array $args, array $assocArgs ): void {
		$dryRun = isset( $assocArgs['dry-run'] );

		$migration = new \CrispySEO\Migrations\InternalLinkJuicer();

		if ( ! $migration->hasData() ) {
			WP_CLI::error( 'No Internal Link Juicer data found.' );
		}

		$count = $migration->getCount();
		WP_CLI::log( sprintf( 'Found %d Internal Link Juicer definitions.', $count ) );

		if ( $dryRun ) {
			$result = $migration->migrate( true );
			WP_CLI::log( sprintf( 'Dry run: Would import %d keywords, skip %d.', $result['imported'], $result['skipped'] ) );
		} else {
			WP_CLI::confirm( 'Proceed with migration?' );
			$result = $migration->migrate( false );
			WP_CLI::success( sprintf( 'Migrated %d keywords, skipped %d.', $result['imported'], $result['skipped'] ) );
		}

		if ( ! empty( $result['errors'] ) ) {
			foreach ( array_slice( $result['errors'], 0, 10 ) as $error ) {
				WP_CLI::warning( $error );
			}
		}
	}
}
