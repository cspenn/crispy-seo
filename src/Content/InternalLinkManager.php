<?php
/**
 * Internal Link Manager
 *
 * Handles automatic internal linking based on keyword-to-post mappings.
 * Uses an index-based approach with transient caching for performance.
 *
 * @package CrispySEO\Content
 * @since 2.0.0
 */

declare(strict_types=1);

namespace CrispySEO\Content;

/**
 * Manages automatic internal linking in post content.
 */
class InternalLinkManager {

	/**
	 * Cache expiration in seconds (24 hours).
	 */
	private const CACHE_EXPIRATION = DAY_IN_SECONDS;

	/**
	 * Maximum links per page setting name.
	 */
	private const MAX_LINKS_OPTION = 'crispy_seo_max_links_per_page';

	/**
	 * Default maximum links per page.
	 */
	private const DEFAULT_MAX_LINKS = 10;

	/**
	 * Keywords table name without prefix.
	 */
	private const KEYWORDS_TABLE = 'crispy_seo_link_keywords';

	/**
	 * Index table name without prefix.
	 */
	private const INDEX_TABLE = 'crispy_seo_link_index';

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->initHooks();
	}

	/**
	 * Initialize WordPress hooks.
	 */
	private function initHooks(): void {
		// Content filtering.
		\add_filter( 'the_content', [ $this, 'processContent' ], 15 );

		// Cache invalidation on post save.
		\add_action( 'save_post', [ $this, 'invalidateCache' ], 10, 2 );
		\add_action( 'delete_post', [ $this, 'deletePostIndex' ] );

		// Admin hooks.
		if ( \is_admin() ) {
			\add_action( 'admin_menu', [ $this, 'registerAdminPage' ] );
			\add_action( 'wp_ajax_crispy_seo_add_keyword', [ $this, 'ajaxAddKeyword' ] );
			\add_action( 'wp_ajax_crispy_seo_delete_keyword', [ $this, 'ajaxDeleteKeyword' ] );
			\add_action( 'wp_ajax_crispy_seo_update_keyword', [ $this, 'ajaxUpdateKeyword' ] );
			\add_action( 'wp_ajax_crispy_seo_get_keywords', [ $this, 'ajaxGetKeywords' ] );
			\add_action( 'wp_ajax_crispy_seo_rebuild_link_index', [ $this, 'ajaxRebuildIndex' ] );
		}

		// Schedule index rebuild.
		\add_action( 'crispy_seo_build_link_index', [ $this, 'rebuildIndex' ] );

		if ( ! \wp_next_scheduled( 'crispy_seo_build_link_index' ) ) {
			\wp_schedule_event( time(), 'daily', 'crispy_seo_build_link_index' );
		}
	}

	/**
	 * Register admin page (called via CrispySEO main menu).
	 */
	public function registerAdminPage(): void {
		add_submenu_page(
			'crispy-seo',
			\__( 'Internal Links', 'crispy-seo' ),
			\__( 'Internal Links', 'crispy-seo' ),
			'manage_options',
			'crispy-seo-internal-links',
			[ $this, 'renderAdminPage' ]
		);
	}

	/**
	 * Render admin page.
	 */
	public function renderAdminPage(): void {
		if ( ! \current_user_can( 'manage_options' ) ) {
			return;
		}
		include CRISPY_SEO_DIR . 'views/admin-internal-links.php';
	}

	/**
	 * Process content and add internal links.
	 *
	 * @param string $content Post content.
	 * @return string Modified content with internal links.
	 */
	public function processContent( string $content ): string {
		// Skip if in admin, feed, or not singular.
		if ( \is_admin() || is_feed() || ! \is_singular() ) {
			return $content;
		}

		$post = \get_post();
		if ( ! $post instanceof \WP_Post ) {
			return $content;
		}

		// Check transient cache.
		$cacheKey = 'crispy_seo_links_' . $post->ID . '_' . md5( $content );
		$cached   = \get_transient( $cacheKey );

		if ( $cached !== false ) {
			return $cached;
		}

		// Process content.
		$processedContent = $this->addLinks( $content, $post->ID );

		// Cache the result.
		\set_transient( $cacheKey, $processedContent, self::CACHE_EXPIRATION );

		return $processedContent;
	}

	/**
	 * Add internal links to content.
	 *
	 * @param string $content Content to process.
	 * @param int    $postId  Current post ID.
	 * @return string Modified content.
	 */
	private function addLinks( string $content, int $postId ): string {
		$keywords = $this->getKeywordsForPost( $postId );

		if ( empty( $keywords ) ) {
			return $content;
		}

		$maxLinksPerPage = (int) \get_option( self::MAX_LINKS_OPTION, self::DEFAULT_MAX_LINKS );
		$totalLinksAdded = 0;
		$linkedKeywords  = [];

		foreach ( $keywords as $keyword ) {
			if ( $totalLinksAdded >= $maxLinksPerPage ) {
				break;
			}

			// Skip if target post is the current post.
			if ( (int) $keyword['target_post_id'] === $postId ) {
				continue;
			}

			// Skip if keyword already linked.
			if ( in_array( strtolower( $keyword['keyword'] ), $linkedKeywords, true ) ) {
				continue;
			}

			// Get target URL.
			$targetUrl = \get_permalink( (int) $keyword['target_post_id'] );
			if ( ! $targetUrl ) {
				continue;
			}

			// Build anchor text.
			$anchorText = ! empty( $keyword['anchor_text'] ) ? $keyword['anchor_text'] : $keyword['keyword'];

			// Calculate how many links to add for this keyword.
			$maxKeywordLinks = min(
				(int) $keyword['max_links_per_page'],
				$maxLinksPerPage - $totalLinksAdded
			);

			// Add links.
			$result = $this->replaceKeywordWithLink(
				$content,
				$keyword['keyword'],
				$targetUrl,
				$anchorText,
				$maxKeywordLinks,
				(bool) $keyword['case_sensitive']
			);

			$content          = $result['content'];
			$totalLinksAdded += $result['links_added'];

			if ( $result['links_added'] > 0 ) {
				$linkedKeywords[] = strtolower( $keyword['keyword'] );
			}
		}

		return $content;
	}

	/**
	 * Replace keyword with link in content.
	 *
	 * @param string $content       Content to process.
	 * @param string $keyword       Keyword to find.
	 * @param string $url           Target URL.
	 * @param string $anchorText    Anchor text.
	 * @param int    $maxLinks      Maximum number of links to add.
	 * @param bool   $caseSensitive Whether to match case-sensitively.
	 * @return array{content: string, links_added: int} Result with modified content and count.
	 */
	private function replaceKeywordWithLink(
		string $content,
		string $keyword,
		string $url,
		string $anchorText,
		int $maxLinks,
		bool $caseSensitive
	): array {
		$linksAdded = 0;

		// Build pattern - match whole words only.
		$escapedKeyword = preg_quote( $keyword, '/' );
		$pattern        = '/\b(' . $escapedKeyword . ')\b/';
		$pattern       .= $caseSensitive ? '' : 'iu';

		// Use DOMDocument for safe HTML manipulation.
		$dom = new \DOMDocument( '1.0', 'UTF-8' );

		// Suppress warnings from malformed HTML.
		libxml_use_internal_errors( true );

		// Wrap content to preserve encoding and structure.
		$wrappedContent = '<html><head><meta charset="UTF-8"></head><body>' . $content . '</body></html>';
		$dom->loadHTML( $wrappedContent, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD );

		libxml_clear_errors();

		// Find all text nodes.
		$xpath     = new \DOMXPath( $dom );
		$textNodes = $xpath->query( '//body//text()[not(ancestor::a) and not(ancestor::script) and not(ancestor::style) and not(ancestor::h1) and not(ancestor::h2) and not(ancestor::h3) and not(ancestor::h4) and not(ancestor::h5) and not(ancestor::h6)]' );

		if ( $textNodes === false ) {
			return [
				'content'     => $content,
				'links_added' => 0,
			];
		}

		$nodesToProcess = [];
		foreach ( $textNodes as $node ) {
			$nodesToProcess[] = $node;
		}

		foreach ( $nodesToProcess as $textNode ) {
			if ( $linksAdded >= $maxLinks ) {
				break;
			}

			$text = $textNode->nodeValue;

			// Check if keyword exists in this text node.
			if ( ! preg_match( $pattern, $text, $matches, PREG_OFFSET_CAPTURE ) ) {
				continue;
			}

			// Get the match position and text.
			$matchText   = $matches[0][0];
			$matchOffset = $matches[0][1];

			// Create a document fragment to hold the replacement nodes.
			$fragment = $dom->createDocumentFragment();

			// Add text before the match (if any).
			$beforeText = substr( $text, 0, $matchOffset );
			if ( $beforeText !== '' && $beforeText !== false ) {
				$fragment->appendChild( $dom->createTextNode( $beforeText ) );
			}

			// Create the anchor element safely using DOM methods.
			$anchor = $dom->createElement( 'a' );
			$anchor->setAttribute( 'href', \esc_url( $url ) );
			$anchor->setAttribute( 'class', 'crispy-internal-link' );
			$anchor->appendChild( $dom->createTextNode( $anchorText ) );
			$fragment->appendChild( $anchor );

			// Add text after the match (if any).
			$afterText = substr( $text, $matchOffset + strlen( $matchText ) );
			if ( $afterText !== '' && $afterText !== false ) {
				$fragment->appendChild( $dom->createTextNode( $afterText ) );
			}

			// Replace the text node with our fragment.
			if ( $textNode->parentNode !== null ) {
				$textNode->parentNode->replaceChild( $fragment, $textNode );
				++$linksAdded;
			}
		}

		// Extract body content.
		$body = $dom->getElementsByTagName( 'body' )->item( 0 );
		if ( $body === null ) {
			return [
				'content'     => $content,
				'links_added' => $linksAdded,
			];
		}

		$result = '';
		foreach ( $body->childNodes as $child ) {
			$result .= $dom->saveHTML( $child );
		}

		return [
			'content'     => $result,
			'links_added' => $linksAdded,
		];
	}

	/**
	 * Get keywords applicable to a post.
	 *
	 * @param int $postId Post ID.
	 * @return array<array<string, mixed>> Keywords data.
	 */
	private function getKeywordsForPost( int $postId ): array {
		global $wpdb;

		$tableName = $wpdb->prefix . self::KEYWORDS_TABLE;

		// Get all enabled keywords ordered by priority.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$keywords = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT * FROM %i WHERE enabled = 1 AND target_post_id != %d ORDER BY priority DESC, id ASC',
				$tableName,
				$postId
			),
			ARRAY_A
		);

		return $keywords ?: [];
	}

	/**
	 * Add a keyword.
	 *
	 * @param string $keyword        Keyword to link.
	 * @param int    $targetPostId   Target post ID.
	 * @param string $anchorText     Custom anchor text (optional).
	 * @param int    $maxLinksPerPage Maximum links per page.
	 * @param bool   $caseSensitive  Case sensitive matching.
	 * @param int    $priority       Priority (higher = first).
	 * @return int|false Insert ID or false on failure.
	 */
	public function addKeyword(
		string $keyword,
		int $targetPostId,
		string $anchorText = '',
		int $maxLinksPerPage = 3,
		bool $caseSensitive = false,
		int $priority = 10
	): int|false {
		global $wpdb;

		$tableName = $wpdb->prefix . self::KEYWORDS_TABLE;

		// Validate target post exists.
		$targetPost = \get_post( $targetPostId );
		if ( ! $targetPost ) {
			return false;
		}

		// Sanitize keyword before duplicate check to ensure consistent comparison.
		$keyword = \sanitize_text_field( $keyword );

		// Check for duplicate keyword.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$existing = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT id FROM %i WHERE keyword = %s',
				$tableName,
				$keyword
			)
		);

		if ( $existing ) {
			return false;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$inserted = $wpdb->insert(
			$tableName,
			[
				'keyword'            => $keyword,
				'target_post_id'     => $targetPostId,
				'anchor_text'        => \sanitize_text_field( $anchorText ),
				'max_links_per_page' => $maxLinksPerPage,
				'case_sensitive'     => $caseSensitive ? 1 : 0,
				'priority'           => $priority,
				'enabled'            => 1,
				'created_at'         => current_time( 'mysql' ),
			],
			[ '%s', '%d', '%s', '%d', '%d', '%d', '%d', '%s' ]
		);

		if ( $inserted ) {
			$this->invalidateAllCaches();
			return (int) $wpdb->insert_id;
		}

		return false;
	}

	/**
	 * Update a keyword.
	 *
	 * @param int   $keywordId Keyword ID.
	 * @param array $data      Data to update.
	 * @return bool Success.
	 */
	public function updateKeyword( int $keywordId, array $data ): bool {
		global $wpdb;

		$tableName = $wpdb->prefix . self::KEYWORDS_TABLE;

		$allowedFields = [
			'keyword',
			'target_post_id',
			'anchor_text',
			'max_links_per_page',
			'case_sensitive',
			'priority',
			'enabled',
		];

		$updateData   = [];
		$updateFormat = [];

		foreach ( $allowedFields as $field ) {
			if ( isset( $data[ $field ] ) ) {
				$updateData[ $field ] = $data[ $field ];

				if ( in_array( $field, [ 'target_post_id', 'max_links_per_page', 'priority', 'case_sensitive', 'enabled' ], true ) ) {
					$updateFormat[] = '%d';
				} else {
					$updateFormat[] = '%s';
				}
			}
		}

		if ( empty( $updateData ) ) {
			return false;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->update(
			$tableName,
			$updateData,
			[ 'id' => $keywordId ],
			$updateFormat,
			[ '%d' ]
		);

		if ( $result !== false ) {
			$this->invalidateAllCaches();
			return true;
		}

		return false;
	}

	/**
	 * Delete a keyword.
	 *
	 * @param int $keywordId Keyword ID.
	 * @return bool Success.
	 */
	public function deleteKeyword( int $keywordId ): bool {
		global $wpdb;

		$tableName = $wpdb->prefix . self::KEYWORDS_TABLE;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->delete(
			$tableName,
			[ 'id' => $keywordId ],
			[ '%d' ]
		);

		if ( $result ) {
			$this->invalidateAllCaches();
			return true;
		}

		return false;
	}

	/**
	 * Get all keywords.
	 *
	 * @param int $limit  Limit results.
	 * @param int $offset Offset for pagination.
	 * @return array{keywords: array<array<string, mixed>>, total: int} Keywords and total count.
	 */
	public function getKeywords( int $limit = 50, int $offset = 0 ): array {
		global $wpdb;

		$tableName = $wpdb->prefix . self::KEYWORDS_TABLE;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$keywords = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT * FROM %i ORDER BY priority DESC, keyword ASC LIMIT %d OFFSET %d',
				$tableName,
				$limit,
				$offset
			),
			ARRAY_A
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$total = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i', $tableName ) );

		// Add post title to each keyword.
		foreach ( $keywords as &$keyword ) {
			$post                  = \get_post( (int) $keyword['target_post_id'] );
			$keyword['post_title'] = $post ? $post->post_title : \__( '(Deleted post)', 'crispy-seo' );
		}

		return [
			'keywords' => $keywords ?: [],
			'total'    => $total,
		];
	}

	/**
	 * Rebuild the link index.
	 */
	public function rebuildIndex(): void {
		global $wpdb;

		$indexTable    = $wpdb->prefix . self::INDEX_TABLE;
		$keywordsTable = $wpdb->prefix . self::KEYWORDS_TABLE;

		// Clear existing index.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query( $wpdb->prepare( 'TRUNCATE TABLE %i', $indexTable ) );

		// Get all published posts.
		$posts = \get_posts(
			[
				'post_type'      => [ 'post', 'page' ],
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'fields'         => 'ids',
			]
		);

		// Get all keywords.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$keywords = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT id, keyword, case_sensitive FROM %i WHERE enabled = 1',
				$keywordsTable
			),
			ARRAY_A
		);

		if ( empty( $posts ) || empty( $keywords ) ) {
			return;
		}

		$now = current_time( 'mysql' );

		foreach ( $posts as $postId ) {
			$content = get_post_field( 'post_content', $postId );

			foreach ( $keywords as $keyword ) {
				$pattern = '/\b' . preg_quote( $keyword['keyword'], '/' ) . '\b/';
				$pattern .= $keyword['case_sensitive'] ? '' : 'iu';

				$count = preg_match_all( $pattern, $content );

				if ( $count > 0 ) {
					// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
					$wpdb->replace(
						$indexTable,
						[
							'post_id'      => $postId,
							'keyword_id'   => (int) $keyword['id'],
							'link_count'   => $count,
							'last_updated' => $now,
						],
						[ '%d', '%d', '%d', '%s' ]
					);
				}
			}
		}

		// Invalidate all caches after rebuild.
		$this->invalidateAllCaches();
	}

	/**
	 * Invalidate cache for a specific post.
	 *
	 * Uses WordPress transient API to properly clear caches including object cache backends.
	 *
	 * @param int      $postId Post ID.
	 * @param \WP_Post $post   Post object.
	 */
	public function invalidateCache( int $postId, \WP_Post $post ): void {
		// Only for public post types.
		if ( ! is_post_type_viewable( $post->post_type ) ) {
			return;
		}

		global $wpdb;

		// Get all transient keys for this post from the database.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$transients = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT option_name FROM %i WHERE option_name LIKE %s",
				$wpdb->options,
				'_transient_crispy_seo_links_' . $postId . '_%'
			)
		);

		// Delete each transient using the proper API (handles object cache).
		foreach ( $transients as $transient ) {
			// Extract the transient name by removing the '_transient_' prefix.
			$transientName = str_replace( '_transient_', '', $transient );
			\delete_transient( $transientName );
		}
	}

	/**
	 * Delete index entries for a post.
	 *
	 * @param int $postId Post ID.
	 */
	public function deletePostIndex( int $postId ): void {
		global $wpdb;

		$tableName = $wpdb->prefix . self::INDEX_TABLE;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->delete( $tableName, [ 'post_id' => $postId ], [ '%d' ] );
	}

	/**
	 * Invalidate all link caches.
	 *
	 * Uses WordPress transient API to properly clear caches including object cache backends.
	 */
	private function invalidateAllCaches(): void {
		global $wpdb;

		// First, try to use wp_cache_flush_group if available (WordPress 6.1+).
		if ( function_exists( 'wp_cache_flush_group' ) ) {
			wp_cache_flush_group( 'crispy_seo_links' );
		}

		// Get all transient keys from the database.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$transients = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT option_name FROM %i WHERE option_name LIKE %s",
				$wpdb->options,
				'_transient_crispy_seo_links_%'
			)
		);

		// Delete each transient using the proper API (handles object cache).
		foreach ( $transients as $transient ) {
			// Extract the transient name by removing the '_transient_' prefix.
			$transientName = str_replace( '_transient_', '', $transient );
			\delete_transient( $transientName );
		}
	}

	/**
	 * AJAX: Add keyword.
	 */
	public function ajaxAddKeyword(): void {
		\check_ajax_referer( 'crispy_seo_internal_links', 'nonce' );

		if ( ! \current_user_can( 'manage_options' ) ) {
			\wp_send_json_error( [ 'message' => \__( 'Permission denied.', 'crispy-seo' ) ] );
		}

		$keyword       = isset( $_POST['keyword'] ) ? \sanitize_text_field( \wp_unslash( $_POST['keyword'] ) ) : '';
		$targetPostId  = isset( $_POST['target_post_id'] ) ? (int) $_POST['target_post_id'] : 0;
		$anchorText    = isset( $_POST['anchor_text'] ) ? \sanitize_text_field( \wp_unslash( $_POST['anchor_text'] ) ) : '';
		$maxLinks      = isset( $_POST['max_links'] ) ? (int) $_POST['max_links'] : 3;
		$caseSensitive = isset( $_POST['case_sensitive'] ) && $_POST['case_sensitive'] === '1';
		$priority      = isset( $_POST['priority'] ) ? (int) $_POST['priority'] : 10;

		if ( empty( $keyword ) || $targetPostId <= 0 ) {
			\wp_send_json_error( [ 'message' => \__( 'Keyword and target post are required.', 'crispy-seo' ) ] );
		}

		$result = $this->addKeyword( $keyword, $targetPostId, $anchorText, $maxLinks, $caseSensitive, $priority );

		if ( $result ) {
			\wp_send_json_success(
				[
					'message' => \__( 'Keyword added successfully.', 'crispy-seo' ),
					'id'      => $result,
				]
			);
		} else {
			\wp_send_json_error( [ 'message' => \__( 'Failed to add keyword. It may already exist.', 'crispy-seo' ) ] );
		}
	}

	/**
	 * AJAX: Delete keyword.
	 */
	public function ajaxDeleteKeyword(): void {
		\check_ajax_referer( 'crispy_seo_internal_links', 'nonce' );

		if ( ! \current_user_can( 'manage_options' ) ) {
			\wp_send_json_error( [ 'message' => \__( 'Permission denied.', 'crispy-seo' ) ] );
		}

		$keywordId = isset( $_POST['keyword_id'] ) ? (int) $_POST['keyword_id'] : 0;

		if ( $keywordId <= 0 ) {
			\wp_send_json_error( [ 'message' => \__( 'Invalid keyword ID.', 'crispy-seo' ) ] );
		}

		if ( $this->deleteKeyword( $keywordId ) ) {
			\wp_send_json_success( [ 'message' => \__( 'Keyword deleted.', 'crispy-seo' ) ] );
		} else {
			\wp_send_json_error( [ 'message' => \__( 'Failed to delete keyword.', 'crispy-seo' ) ] );
		}
	}

	/**
	 * AJAX: Update keyword.
	 */
	public function ajaxUpdateKeyword(): void {
		\check_ajax_referer( 'crispy_seo_internal_links', 'nonce' );

		if ( ! \current_user_can( 'manage_options' ) ) {
			\wp_send_json_error( [ 'message' => \__( 'Permission denied.', 'crispy-seo' ) ] );
		}

		$keywordId = isset( $_POST['keyword_id'] ) ? (int) $_POST['keyword_id'] : 0;

		if ( $keywordId <= 0 ) {
			\wp_send_json_error( [ 'message' => \__( 'Invalid keyword ID.', 'crispy-seo' ) ] );
		}

		$data = [];

		if ( isset( $_POST['enabled'] ) ) {
			$data['enabled'] = $_POST['enabled'] === '1' ? 1 : 0;
		}
		if ( isset( $_POST['priority'] ) ) {
			$data['priority'] = (int) $_POST['priority'];
		}
		if ( isset( $_POST['max_links'] ) ) {
			$data['max_links_per_page'] = (int) $_POST['max_links'];
		}

		if ( $this->updateKeyword( $keywordId, $data ) ) {
			\wp_send_json_success( [ 'message' => \__( 'Keyword updated.', 'crispy-seo' ) ] );
		} else {
			\wp_send_json_error( [ 'message' => \__( 'Failed to update keyword.', 'crispy-seo' ) ] );
		}
	}

	/**
	 * AJAX: Get keywords list.
	 */
	public function ajaxGetKeywords(): void {
		\check_ajax_referer( 'crispy_seo_internal_links', 'nonce' );

		if ( ! \current_user_can( 'manage_options' ) ) {
			\wp_send_json_error( [ 'message' => \__( 'Permission denied.', 'crispy-seo' ) ] );
		}

		$page    = isset( $_POST['page'] ) ? max( 1, (int) $_POST['page'] ) : 1;
		$perPage = 50;
		$offset  = ( $page - 1 ) * $perPage;

		$result = $this->getKeywords( $perPage, $offset );

		\wp_send_json_success(
			[
				'keywords'   => $result['keywords'],
				'total'      => $result['total'],
				'pages'      => ceil( $result['total'] / $perPage ),
				'current'    => $page,
			]
		);
	}

	/**
	 * AJAX: Rebuild link index.
	 */
	public function ajaxRebuildIndex(): void {
		\check_ajax_referer( 'crispy_seo_internal_links', 'nonce' );

		if ( ! \current_user_can( 'manage_options' ) ) {
			\wp_send_json_error( [ 'message' => \__( 'Permission denied.', 'crispy-seo' ) ] );
		}

		$this->rebuildIndex();

		\wp_send_json_success( [ 'message' => \__( 'Link index rebuilt successfully.', 'crispy-seo' ) ] );
	}

	/**
	 * Get statistics.
	 *
	 * @return array{total_keywords: int, enabled_keywords: int, indexed_posts: int} Statistics.
	 */
	public function getStats(): array {
		global $wpdb;

		$keywordsTable = $wpdb->prefix . self::KEYWORDS_TABLE;
		$indexTable    = $wpdb->prefix . self::INDEX_TABLE;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$totalKeywords = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i', $keywordsTable ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$enabledKeywords = (int) $wpdb->get_var(
			$wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE enabled = 1', $keywordsTable )
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$indexedPosts = (int) $wpdb->get_var(
			$wpdb->prepare( 'SELECT COUNT(DISTINCT post_id) FROM %i', $indexTable )
		);

		return [
			'total_keywords'   => $totalKeywords,
			'enabled_keywords' => $enabledKeywords,
			'indexed_posts'    => $indexedPosts,
		];
	}
}
