<?php
/**
 * 404 Not Found Manager
 *
 * Handles custom 404 pages and 404 error tracking/analytics.
 *
 * @package CrispySEO\Technical
 * @since 2.1.0
 */

declare(strict_types=1);

namespace CrispySEO\Technical;

/**
 * Manages custom 404 pages and 404 hit logging.
 */
class NotFoundManager {

	/**
	 * Cache key prefix for object cache.
	 */
	private const CACHE_KEY = 'crispy_seo_404';

	/**
	 * Cache expiration in seconds (10 minutes).
	 */
	private const CACHE_EXPIRATION = 600;

	/**
	 * Known bot user agent patterns.
	 *
	 * @var array<string>
	 */
	private const BOT_PATTERNS = [
		'Googlebot',
		'bingbot',
		'Slurp',
		'DuckDuckBot',
		'Baiduspider',
		'YandexBot',
		'facebookexternalhit',
		'Twitterbot',
		'LinkedInBot',
		'WhatsApp',
		'Applebot',
		'AhrefsBot',
		'SemrushBot',
		'MJ12bot',
		'DotBot',
		'PetalBot',
		'Bytespider',
	];

	/**
	 * Constructor.
	 */
	public function __construct() {
		// Ensure table exists (handles case where plugin updated without reactivation).
		$this->ensureTableExists();

		add_action( 'template_redirect', [ $this, 'handleNotFound' ], 1 );

		// AJAX handlers.
		add_action( 'wp_ajax_crispy_seo_get_404_logs', [ $this, 'ajaxGetLogs' ] );
		add_action( 'wp_ajax_crispy_seo_delete_404_logs', [ $this, 'ajaxDeleteLogs' ] );
		add_action( 'wp_ajax_crispy_seo_purge_404_logs', [ $this, 'ajaxPurgeLogs' ] );
		add_action( 'wp_ajax_crispy_seo_create_redirect_from_404', [ $this, 'ajaxCreateRedirect' ] );
		add_action( 'wp_ajax_crispy_seo_save_404_settings', [ $this, 'ajaxSaveSettings' ] );

		// Scheduled cleanup.
		add_action( 'crispy_seo_cleanup_404_logs', [ $this, 'scheduledCleanup' ] );
	}

	/**
	 * Ensure the 404 logs table exists.
	 *
	 * This handles the case where the plugin was updated without reactivation,
	 * which means the activation hook never ran to create the table.
	 *
	 * @return void
	 */
	private function ensureTableExists(): void {
		$installer = new NotFoundInstaller();
		if ( ! $installer->tableExists() ) {
			$installer->install();
		}
	}

	/**
	 * Handle 404 errors.
	 *
	 * Logs the 404 hit and optionally serves a custom 404 page.
	 *
	 * @return void
	 */
	public function handleNotFound(): void {
		if ( ! is_404() ) {
			return;
		}

		// Log the 404 hit.
		if ( $this->shouldLogHit() ) {
			$this->logHit(
				isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '',
				isset( $_SERVER['HTTP_REFERER'] ) ? esc_url_raw( wp_unslash( $_SERVER['HTTP_REFERER'] ) ) : '',
				isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : ''
			);
		}

		// Serve custom 404 page if configured.
		$customPageId = $this->getCustom404PageId();
		if ( $customPageId > 0 ) {
			$this->serveCustom404Page( $customPageId );
		}
	}

	/**
	 * Check if we should log this 404 hit.
	 *
	 * @return bool True if hit should be logged.
	 */
	private function shouldLogHit(): bool {
		// Skip if logging is disabled.
		if ( ! get_option( 'crispy_seo_404_log_enabled', true ) ) {
			return false;
		}

		// Skip logged-in administrators.
		if ( current_user_can( 'manage_options' ) ) {
			return false;
		}

		// Skip known bots.
		$userAgent = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';
		foreach ( self::BOT_PATTERNS as $bot ) {
			if ( stripos( $userAgent, $bot ) !== false ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Log a 404 hit.
	 *
	 * @param string $requestUri Request URI.
	 * @param string $referrer   HTTP referrer.
	 * @param string $userAgent  User agent string.
	 * @return bool True on success.
	 */
	public function logHit( string $requestUri, string $referrer, string $userAgent ): bool {
		global $wpdb;

		// Parse URL into path and query.
		$parsed = wp_parse_url( $requestUri );
		$path   = $parsed['path'] ?? '/';
		$query  = $parsed['query'] ?? null;

		// Get client IP (anonymized for GDPR).
		$ipAddress = $this->getAnonymizedIp();

		$tableName = NotFoundInstaller::getTableName();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Logging operation.
		$result = $wpdb->insert(
			$tableName,
			[
				'request_path'  => substr( $path, 0, 500 ),
				'request_query' => $query ? substr( $query, 0, 1000 ) : null,
				'referrer'      => $referrer ? substr( $referrer, 0, 2000 ) : null,
				'user_agent'    => $userAgent ? substr( $userAgent, 0, 500 ) : null,
				'ip_address'    => $ipAddress,
				'created_at'    => current_time( 'mysql', true ),
			],
			[ '%s', '%s', '%s', '%s', '%s', '%s' ]
		);

		// Clear stats cache on new log.
		wp_cache_delete( self::CACHE_KEY . '_stats', 'crispy_seo' );

		return $result !== false;
	}

	/**
	 * Get anonymized IP address for GDPR compliance.
	 *
	 * Removes last octet for IPv4 or last 80 bits for IPv6.
	 *
	 * @return string|null Anonymized IP or null.
	 */
	private function getAnonymizedIp(): ?string {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : null;

		if ( ! $ip ) {
			return null;
		}

		// Validate IP.
		if ( ! filter_var( $ip, FILTER_VALIDATE_IP ) ) {
			return null;
		}

		// Anonymize IPv4 (remove last octet).
		if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 ) ) {
			$parts    = explode( '.', $ip );
			$parts[3] = '0';
			return implode( '.', $parts );
		}

		// Anonymize IPv6 (remove last 80 bits = last 5 groups).
		if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 ) ) {
			$parts = explode( ':', $ip );
			for ( $i = 3; $i < 8; $i++ ) {
				$parts[ $i ] = '0';
			}
			return implode( ':', $parts );
		}

		return null;
	}

	/**
	 * Get custom 404 page ID from settings.
	 *
	 * @return int Page ID or 0 if not set.
	 */
	public function getCustom404PageId(): int {
		return (int) get_option( 'crispy_seo_404_page_id', 0 );
	}

	/**
	 * Serve custom 404 page content while preserving 404 status.
	 *
	 * @param int $pageId WordPress page ID.
	 * @return void
	 */
	private function serveCustom404Page( int $pageId ): void {
		global $wp_query, $post;

		// Get custom page.
		$customPage = get_post( $pageId );
		if ( ! $customPage || $customPage->post_status !== 'publish' ) {
			return;
		}

		// Set 404 status header.
		status_header( 404 );
		nocache_headers();

		// Setup post data.
		$post = $customPage; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Intentional for custom 404.
		$wp_query->post       = $customPage;
		$wp_query->posts      = [ $customPage ];
		$wp_query->post_count = 1;
		$wp_query->is_404     = true;
		$wp_query->is_page    = true;
		$wp_query->is_single  = true;

		setup_postdata( $customPage );

		// Load page template.
		$template = get_page_template();
		if ( $template && file_exists( $template ) ) {
			include $template;
			exit;
		}

		// Fall back to singular template.
		$template = get_singular_template();
		if ( $template && file_exists( $template ) ) {
			include $template;
			exit;
		}
	}

	/**
	 * Get 404 statistics.
	 *
	 * @return array{total: int, unique_urls: int, today: int}
	 */
	public function getStats(): array {
		$cached = wp_cache_get( self::CACHE_KEY . '_stats', 'crispy_seo' );
		if ( $cached !== false ) {
			return $cached;
		}

		global $wpdb;
		$tableName = NotFoundInstaller::getTableName();
		$today     = gmdate( 'Y-m-d' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom query with caching.
		$stats = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT
					COUNT(*) as total,
					COUNT(DISTINCT request_path) as unique_urls,
					SUM(CASE WHEN DATE(created_at) = %s THEN 1 ELSE 0 END) as today
				FROM {$tableName}",
				$today
			),
			ARRAY_A
		);

		$result = [
			'total'       => (int) ( $stats['total'] ?? 0 ),
			'unique_urls' => (int) ( $stats['unique_urls'] ?? 0 ),
			'today'       => (int) ( $stats['today'] ?? 0 ),
		];

		wp_cache_set( self::CACHE_KEY . '_stats', $result, 'crispy_seo', self::CACHE_EXPIRATION );

		return $result;
	}

	/**
	 * Get top 404 URLs by hit count.
	 *
	 * @param int $limit Maximum number of results.
	 * @return array<array{request_path: string, hit_count: int, last_seen: string}>
	 */
	public function getTopUrls( int $limit = 50 ): array {
		global $wpdb;
		$tableName = NotFoundInstaller::getTableName();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Analytics query.
		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
					request_path,
					COUNT(*) as hit_count,
					MAX(created_at) as last_seen
				FROM {$tableName}
				GROUP BY request_path
				ORDER BY hit_count DESC
				LIMIT %d",
				$limit
			),
			ARRAY_A
		);

		return is_array( $results ) ? $results : [];
	}

	/**
	 * Get 404 logs with optional filters.
	 *
	 * @param array{limit?: int, offset?: int, orderby?: string, order?: string} $filters Query filters.
	 * @return array<array{id: int, request_path: string, request_query: string|null, referrer: string|null, user_agent: string|null, created_at: string}>
	 */
	public function getLogs( array $filters = [] ): array {
		global $wpdb;
		$tableName = NotFoundInstaller::getTableName();

		$limit   = isset( $filters['limit'] ) ? absint( $filters['limit'] ) : 100;
		$offset  = isset( $filters['offset'] ) ? absint( $filters['offset'] ) : 0;
		$orderby = isset( $filters['orderby'] ) && in_array( $filters['orderby'], [ 'created_at', 'request_path' ], true )
			? $filters['orderby']
			: 'created_at';
		$order   = isset( $filters['order'] ) && strtoupper( $filters['order'] ) === 'ASC' ? 'ASC' : 'DESC';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Analytics query.
		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, request_path, request_query, referrer, user_agent, ip_address, created_at
				FROM {$tableName}
				ORDER BY {$orderby} {$order}
				LIMIT %d OFFSET %d",
				$limit,
				$offset
			),
			ARRAY_A
		);

		return is_array( $results ) ? $results : [];
	}

	/**
	 * Get a single log entry by ID.
	 *
	 * @param int $logId Log ID.
	 * @return array<string, mixed>|null Log entry or null.
	 */
	public function getLogById( int $logId ): ?array {
		global $wpdb;
		$tableName = NotFoundInstaller::getTableName();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Single record fetch.
		$result = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$tableName} WHERE id = %d",
				$logId
			),
			ARRAY_A
		);

		return is_array( $result ) ? $result : null;
	}

	/**
	 * Delete specific log entries.
	 *
	 * @param array<int> $logIds Log IDs to delete.
	 * @return int Number of deleted records.
	 */
	public function deleteLogs( array $logIds ): int {
		if ( empty( $logIds ) ) {
			return 0;
		}

		global $wpdb;
		$tableName = NotFoundInstaller::getTableName();

		// Sanitize IDs.
		$ids         = array_map( 'absint', $logIds );
		$ids         = array_filter( $ids );
		$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Delete operation.
		$deleted = $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$tableName} WHERE id IN ({$placeholders})", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Placeholders are safe.
				...$ids
			)
		);

		// Clear cache.
		wp_cache_delete( self::CACHE_KEY . '_stats', 'crispy_seo' );

		return is_int( $deleted ) ? $deleted : 0;
	}

	/**
	 * Delete all logs for a specific path.
	 *
	 * @param string $path Request path.
	 * @return int Number of deleted records.
	 */
	public function deleteLogsByPath( string $path ): int {
		global $wpdb;
		$tableName = NotFoundInstaller::getTableName();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Delete operation.
		$deleted = $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$tableName} WHERE request_path = %s",
				$path
			)
		);

		// Clear cache.
		wp_cache_delete( self::CACHE_KEY . '_stats', 'crispy_seo' );

		return is_int( $deleted ) ? $deleted : 0;
	}

	/**
	 * Purge all 404 logs.
	 *
	 * @return int Number of deleted records.
	 */
	public function purgeAllLogs(): int {
		global $wpdb;
		$tableName = NotFoundInstaller::getTableName();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Purge operation.
		$deleted = $wpdb->query( "TRUNCATE TABLE {$tableName}" );

		// Clear cache.
		wp_cache_delete( self::CACHE_KEY . '_stats', 'crispy_seo' );

		return is_int( $deleted ) ? $deleted : 0;
	}

	/**
	 * Create a redirect from a 404 log entry.
	 *
	 * @param int    $logId      Log ID.
	 * @param string $targetUrl  Target URL for redirect.
	 * @param int    $type       Redirect type (301, 302, etc).
	 * @return bool True on success.
	 */
	public function createRedirectFromLog( int $logId, string $targetUrl, int $type = 301 ): bool {
		$log = $this->getLogById( $logId );
		if ( ! $log ) {
			return false;
		}

		$redirectManager = crispy_seo()->getComponent( 'redirect_manager' );
		if ( ! $redirectManager instanceof RedirectManager ) {
			return false;
		}

		$result = $redirectManager->addRedirect(
			$log['request_path'],
			$targetUrl,
			$type,
			'exact'
		);

		if ( $result !== false ) {
			// Delete all logs for this URL since it's now redirected.
			$this->deleteLogsByPath( $log['request_path'] );
			return true;
		}

		return false;
	}

	/**
	 * Scheduled cleanup handler.
	 *
	 * @return void
	 */
	public function scheduledCleanup(): void {
		$retentionDays = (int) get_option( 'crispy_seo_404_log_retention_days', 30 );
		$installer     = new NotFoundInstaller();
		$installer->cleanupOldLogs( $retentionDays );
	}

	/**
	 * AJAX: Get 404 logs.
	 *
	 * @return void
	 */
	public function ajaxGetLogs(): void {
		check_ajax_referer( 'crispy_seo_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'Unauthorized.', 'crispy-seo' ) ] );
		}

		$limit  = isset( $_POST['limit'] ) ? absint( $_POST['limit'] ) : 100;
		$offset = isset( $_POST['offset'] ) ? absint( $_POST['offset'] ) : 0;

		$logs  = $this->getLogs( [ 'limit' => $limit, 'offset' => $offset ] );
		$stats = $this->getStats();

		wp_send_json_success( [
			'logs'  => $logs,
			'stats' => $stats,
		] );
	}

	/**
	 * AJAX: Delete specific logs.
	 *
	 * @return void
	 */
	public function ajaxDeleteLogs(): void {
		check_ajax_referer( 'crispy_seo_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'Unauthorized.', 'crispy-seo' ) ] );
		}

		$ids = isset( $_POST['ids'] ) ? array_map( 'absint', (array) $_POST['ids'] ) : [];

		if ( empty( $ids ) ) {
			wp_send_json_error( [ 'message' => __( 'No log IDs provided.', 'crispy-seo' ) ] );
		}

		$deleted = $this->deleteLogs( $ids );

		wp_send_json_success( [
			'message' => sprintf(
				/* translators: %d: number of deleted logs */
				__( 'Deleted %d log entries.', 'crispy-seo' ),
				$deleted
			),
			'deleted' => $deleted,
		] );
	}

	/**
	 * AJAX: Purge all logs.
	 *
	 * @return void
	 */
	public function ajaxPurgeLogs(): void {
		check_ajax_referer( 'crispy_seo_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'Unauthorized.', 'crispy-seo' ) ] );
		}

		$this->purgeAllLogs();

		wp_send_json_success( [
			'message' => __( 'All 404 logs have been purged.', 'crispy-seo' ),
		] );
	}

	/**
	 * AJAX: Create redirect from 404 log.
	 *
	 * @return void
	 */
	public function ajaxCreateRedirect(): void {
		check_ajax_referer( 'crispy_seo_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'Unauthorized.', 'crispy-seo' ) ] );
		}

		$logId     = isset( $_POST['log_id'] ) ? absint( $_POST['log_id'] ) : 0;
		$targetUrl = isset( $_POST['target_url'] ) ? esc_url_raw( wp_unslash( $_POST['target_url'] ) ) : '';
		$type      = isset( $_POST['redirect_type'] ) ? absint( $_POST['redirect_type'] ) : 301;

		if ( ! $logId ) {
			wp_send_json_error( [ 'message' => __( 'Invalid log ID.', 'crispy-seo' ) ] );
		}

		if ( empty( $targetUrl ) ) {
			wp_send_json_error( [ 'message' => __( 'Target URL is required.', 'crispy-seo' ) ] );
		}

		$result = $this->createRedirectFromLog( $logId, $targetUrl, $type );

		if ( $result ) {
			wp_send_json_success( [
				'message' => __( 'Redirect created successfully.', 'crispy-seo' ),
			] );
		} else {
			wp_send_json_error( [
				'message' => __( 'Failed to create redirect.', 'crispy-seo' ),
			] );
		}
	}

	/**
	 * AJAX: Save 404 settings.
	 *
	 * @return void
	 */
	public function ajaxSaveSettings(): void {
		check_ajax_referer( 'crispy_seo_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'Unauthorized.', 'crispy-seo' ) ] );
		}

		$pageId        = isset( $_POST['page_id'] ) ? absint( $_POST['page_id'] ) : 0;
		$retentionDays = isset( $_POST['retention_days'] ) ? absint( $_POST['retention_days'] ) : 30;
		$logEnabled    = isset( $_POST['log_enabled'] ) && $_POST['log_enabled'] === '1';

		update_option( 'crispy_seo_404_page_id', $pageId );
		update_option( 'crispy_seo_404_log_retention_days', max( 1, min( 365, $retentionDays ) ) );
		update_option( 'crispy_seo_404_log_enabled', $logEnabled );

		wp_send_json_success( [
			'message' => __( 'Settings saved successfully.', 'crispy-seo' ),
		] );
	}
}
