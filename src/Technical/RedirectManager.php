<?php
/**
 * Enhanced Redirect Manager
 *
 * Database-backed redirect management with regex support,
 * hit tracking, and CSV import/export.
 *
 * @package CrispySEO\Technical
 * @since 2.0.0
 */

declare(strict_types=1);

namespace CrispySEO\Technical;

/**
 * Manages 301/302/307/308/410/451 redirects with database storage.
 */
class RedirectManager {

	/**
	 * Supported redirect types.
	 */
	public const REDIRECT_TYPES = [ 301, 302, 307, 308, 410, 451 ];

	/**
	 * Match types for redirect patterns.
	 */
	public const MATCH_TYPES = [ 'exact', 'wildcard', 'regex' ];

	/**
	 * Redirect ID for hit tracking (queued for async update).
	 *
	 * @var int|null
	 */
	private ?int $hitRedirectId = null;

	/**
	 * Cache key prefix for object cache.
	 */
	private const CACHE_KEY = 'crispy_seo_redirects';

	/**
	 * Cache expiration in seconds (10 minutes).
	 */
	private const CACHE_EXPIRATION = 600;

	/**
	 * Constructor.
	 */
	public function __construct() {
		\add_action( 'template_redirect', [ $this, 'handleRedirect' ], 1 );
		\add_action( 'shutdown', [ $this, 'recordHit' ] );

		// AJAX handlers.
		\add_action( 'wp_ajax_crispy_seo_save_redirect', [ $this, 'ajaxSaveRedirect' ] );
		\add_action( 'wp_ajax_crispy_seo_delete_redirect', [ $this, 'ajaxDeleteRedirect' ] );
		\add_action( 'wp_ajax_crispy_seo_import_redirects', [ $this, 'ajaxImportRedirects' ] );
		\add_action( 'wp_ajax_crispy_seo_export_redirects', [ $this, 'ajaxExportRedirects' ] );
	}

	/**
	 * Handle redirect if URL matches.
	 */
	public function handleRedirect(): void {
		if ( \is_admin() ) {
			return;
		}

		$currentPath = $this->getCurrentPath();
		$match       = $this->findMatch( $currentPath );

		if ( $match === null ) {
			return;
		}

		// Queue hit tracking for async update.
		$this->hitRedirectId = (int) $match['id'];

		$type   = (int) $match['redirect_type'];
		$target = $match['target_url'];

		// Handle 410 Gone - no redirect, just status code.
		if ( $type === 410 ) {
			\status_header( 410 );
			\nocache_headers();
			include get_query_template( '410' ) ?: get_query_template( '404' );
			exit;
		}

		// Handle 451 Unavailable For Legal Reasons.
		if ( $type === 451 ) {
			\status_header( 451 );
			\nocache_headers();
			include get_query_template( '451' ) ?: get_query_template( '404' );
			exit;
		}

		// Handle relative targets.
		if ( str_starts_with( $target, '/' ) ) {
			$target = \home_url( $target );
		}

		\wp_redirect( $target, $type );
		exit;
	}

	/**
	 * Get current request path.
	 *
	 * @return string Current path.
	 */
	private function getCurrentPath(): string {
		$path = isset( $_SERVER['REQUEST_URI'] ) ? \sanitize_text_field( \wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '/';

		// Parse URL to get just the path.
		$parsed = wp_parse_url( $path );

		return $parsed['path'] ?? '/';
	}

	/**
	 * Find a matching redirect for the given path.
	 *
	 * @param string $path Request path.
	 * @return array<string, mixed>|null Matching redirect or null.
	 */
	public function findMatch( string $path ): ?array {
		$redirects = $this->getEnabledRedirects();

		foreach ( $redirects as $redirect ) {
			if ( $this->matchesPath( $path, $redirect['source_path'], $redirect['match_type'] ) ) {
				return $redirect;
			}
		}

		return null;
	}

	/**
	 * Check if path matches redirect source.
	 *
	 * @param string $path      Current path.
	 * @param string $source    Redirect source pattern.
	 * @param string $matchType Match type (exact, wildcard, regex).
	 * @return bool True if path matches.
	 */
	private function matchesPath( string $path, string $source, string $matchType ): bool {
		switch ( $matchType ) {
			case 'exact':
				// Exact match with trailing slash normalization.
				if ( $path === $source ) {
					return true;
				}
				return rtrim( $path, '/' ) === rtrim( $source, '/' );

			case 'wildcard':
				// Wildcard matching (source ends with *).
				$prefix = rtrim( $source, '*' );
				return str_starts_with( $path, $prefix );

			case 'regex':
				// Regex matching with ReDoS protection.
				return $this->safeRegexMatch( $source, $path );

			default:
				return false;
		}
	}

	/**
	 * Prepare regex pattern for matching.
	 *
	 * @param string $source Source pattern.
	 * @return string Prepared regex pattern.
	 */
	private function prepareRegexPattern( string $source ): string {
		// If pattern already has delimiters, use as-is.
		if ( preg_match( '/^[#@\/~].*[#@\/~][a-zA-Z]*$/', $source ) ) {
			return $source;
		}

		// Add delimiters if not present.
		return '#' . $source . '#';
	}


	/**
	 * Safely execute regex match with ReDoS protection.
	 *
	 * Limits backtracking to prevent catastrophic regex execution times.
	 *
	 * @param string $source Source pattern.
	 * @param string $path   Path to match against.
	 * @return bool True if pattern matches path.
	 */
	private function safeRegexMatch( string $source, string $path ): bool {
		$pattern = $this->prepareRegexPattern( $source );

		// Store original limits.
		$originalBacktrackLimit = (int) ini_get( 'pcre.backtrack_limit' );
		$originalRecursionLimit = (int) ini_get( 'pcre.recursion_limit' );

		// Set protective limits to prevent ReDoS.
		// 10000 backtrack steps is enough for reasonable patterns but prevents catastrophic backtracking.
		ini_set( 'pcre.backtrack_limit', '10000' );
		ini_set( 'pcre.recursion_limit', '1000' );

		// Execute the match with error suppression.
		// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Intentional suppression for validation.
		$result = @preg_match( $pattern, $path );

		// Restore original limits.
		ini_set( 'pcre.backtrack_limit', (string) $originalBacktrackLimit );
		ini_set( 'pcre.recursion_limit', (string) $originalRecursionLimit );

		// preg_match returns 1 for match, 0 for no match, false on error.
		// We treat errors (including backtrack limit exceeded) as no match.
		return $result === 1;
	}

	/**
	 * Get all enabled redirects (cached).
	 *
	 * @return array<array<string, mixed>> Enabled redirects.
	 */
	private function getEnabledRedirects(): array {
		$cached = \wp_cache_get( self::CACHE_KEY . '_enabled', 'crispy_seo' );

		if ( $cached !== false ) {
			return $cached;
		}

		global $wpdb;
		$tableName = RedirectInstaller::getTableName();

		// Check if table exists.
		if ( ! $this->tableExists() ) {
			return [];
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom caching implemented.
		$redirects = $wpdb->get_results(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name from trusted source, using %i for WP 6.2+ compatibility.
			$wpdb->prepare( 'SELECT * FROM %i WHERE enabled = 1 ORDER BY id ASC', $tableName ),
			ARRAY_A
		);

		$redirects = $redirects ?: [];

		\wp_cache_set( self::CACHE_KEY . '_enabled', $redirects, 'crispy_seo', self::CACHE_EXPIRATION );

		return $redirects;
	}

	/**
	 * Check if the redirects table exists.
	 *
	 * @return bool True if table exists.
	 */
	private function tableExists(): bool {
		global $wpdb;
		$tableName = RedirectInstaller::getTableName();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Schema check.
		$exists = $wpdb->get_var(
			$wpdb->prepare( 'SHOW TABLES LIKE %s', $tableName )
		);

		return $exists === $tableName;
	}

	/**
	 * Record hit for matched redirect (called on shutdown).
	 */
	public function recordHit(): void {
		if ( $this->hitRedirectId === null ) {
			return;
		}

		global $wpdb;
		$tableName = RedirectInstaller::getTableName();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Low-overhead update.
		$wpdb->query(
			$wpdb->prepare(
				'UPDATE %i SET hit_count = hit_count + 1, last_hit = %s WHERE id = %d',
				$tableName,
				current_time( 'mysql' ),
				$this->hitRedirectId
			)
		);
	}

	/**
	 * Get all redirects with optional filtering.
	 *
	 * @param array<string, mixed> $filters Optional filters (enabled, match_type, search).
	 * @return array<array<string, mixed>> Redirects.
	 */
	public function getRedirects( array $filters = [] ): array {
		global $wpdb;
		$tableName = RedirectInstaller::getTableName();

		if ( ! $this->tableExists() ) {
			return [];
		}

		$where  = [];
		$values = [ $tableName ]; // First value is table name for %i.

		if ( isset( $filters['enabled'] ) ) {
			$where[]  = 'enabled = %d';
			$values[] = (int) $filters['enabled'];
		}

		if ( isset( $filters['match_type'] ) && in_array( $filters['match_type'], self::MATCH_TYPES, true ) ) {
			$where[]  = 'match_type = %s';
			$values[] = $filters['match_type'];
		}

		if ( isset( $filters['redirect_type'] ) && in_array( (int) $filters['redirect_type'], self::REDIRECT_TYPES, true ) ) {
			$where[]  = 'redirect_type = %d';
			$values[] = (int) $filters['redirect_type'];
		}

		if ( ! empty( $filters['search'] ) ) {
			$search   = '%' . $wpdb->esc_like( $filters['search'] ) . '%';
			$where[]  = '(source_path LIKE %s OR target_url LIKE %s OR notes LIKE %s)';
			$values[] = $search;
			$values[] = $search;
			$values[] = $search;
		}

		$whereClause = ! empty( $where ) ? 'WHERE ' . implode( ' AND ', $where ) : '';

		$orderBy = 'ORDER BY created_at DESC';
		if ( isset( $filters['orderby'] ) ) {
			$allowedColumns = [ 'source_path', 'hit_count', 'created_at', 'last_hit' ];
			if ( in_array( $filters['orderby'], $allowedColumns, true ) ) {
				$order   = ( isset( $filters['order'] ) && strtoupper( $filters['order'] ) === 'ASC' ) ? 'ASC' : 'DESC';
				$orderBy = "ORDER BY {$filters['orderby']} {$order}";
			}
		}

		$limitClause = '';
		if ( isset( $filters['limit'] ) ) {
			$limitClause = 'LIMIT %d';
			$values[]    = (int) $filters['limit'];
			if ( isset( $filters['offset'] ) ) {
				$limitClause = 'LIMIT %d, %d';
				// Insert offset before limit in values array.
				array_pop( $values );
				$values[] = (int) $filters['offset'];
				$values[] = (int) $filters['limit'];
			}
		}

		// Build query with %i for table identifier (WordPress 6.2+).
		$sql = "SELECT * FROM %i {$whereClause} {$orderBy} {$limitClause}";

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Dynamic filtered query with %i for table.
		return $wpdb->get_results( $wpdb->prepare( $sql, $values ), ARRAY_A ) ?: [];
	}

	/**
	 * Add a new redirect.
	 *
	 * @param string $source    Source path.
	 * @param string $target    Target URL.
	 * @param int    $type      Redirect type (301, 302, etc.).
	 * @param string $matchType Match type (exact, wildcard, regex).
	 * @param string $notes     Optional notes.
	 * @return int|false Insert ID on success, false on failure.
	 */
	public function addRedirect( string $source, string $target, int $type = 301, string $matchType = 'exact', string $notes = '' ): int|false {
		global $wpdb;
		$tableName = RedirectInstaller::getTableName();

		// Validate inputs.
		if ( empty( $source ) || empty( $target ) ) {
			return false;
		}

		if ( ! in_array( $type, self::REDIRECT_TYPES, true ) ) {
			$type = 301;
		}

		if ( ! in_array( $matchType, self::MATCH_TYPES, true ) ) {
			$matchType = 'exact';
		}

		// Validate regex pattern if regex match type.
		if ( $matchType === 'regex' && ! $this->isValidRegex( $source ) ) {
			return false;
		}

		$now = current_time( 'mysql' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Insert operation.
		$inserted = $wpdb->insert(
			$tableName,
			[
				'source_path'   => \sanitize_text_field( $source ),
				'target_url'    => esc_url_raw( $target ),
				'redirect_type' => $type,
				'match_type'    => $matchType,
				'notes'         => sanitize_textarea_field( $notes ),
				'created_at'    => $now,
				'updated_at'    => $now,
				'enabled'       => 1,
			],
			[ '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%d' ]
		);

		if ( $inserted ) {
			$this->clearCache();
			return (int) $wpdb->insert_id;
		}

		return false;
	}

	/**
	 * Update an existing redirect.
	 *
	 * @param int                  $id   Redirect ID.
	 * @param array<string, mixed> $data Data to update.
	 * @return bool True on success.
	 */
	public function updateRedirect( int $id, array $data ): bool {
		global $wpdb;
		$tableName = RedirectInstaller::getTableName();

		$allowed = [
			'source_path'   => '%s',
			'target_url'    => '%s',
			'redirect_type' => '%d',
			'match_type'    => '%s',
			'notes'         => '%s',
			'enabled'       => '%d',
		];

		$updateData   = [];
		$updateFormat = [];

		foreach ( $allowed as $field => $format ) {
			if ( isset( $data[ $field ] ) ) {
				// Sanitize based on field type.
				switch ( $field ) {
					case 'source_path':
						$updateData[ $field ] = \sanitize_text_field( $data[ $field ] );
						break;
					case 'target_url':
						$updateData[ $field ] = esc_url_raw( $data[ $field ] );
						break;
					case 'notes':
						$updateData[ $field ] = sanitize_textarea_field( $data[ $field ] );
						break;
					case 'redirect_type':
						$value = (int) $data[ $field ];
						if ( in_array( $value, self::REDIRECT_TYPES, true ) ) {
							$updateData[ $field ] = $value;
						}
						break;
					case 'match_type':
						if ( in_array( $data[ $field ], self::MATCH_TYPES, true ) ) {
							$updateData[ $field ] = $data[ $field ];
						}
						break;
					case 'enabled':
						$updateData[ $field ] = (int) (bool) $data[ $field ];
						break;
				}

				if ( isset( $updateData[ $field ] ) ) {
					$updateFormat[] = $format;
				}
			}
		}

		if ( empty( $updateData ) ) {
			return false;
		}

		$updateData['updated_at'] = current_time( 'mysql' );
		$updateFormat[]           = '%s';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Update operation.
		$updated = $wpdb->update(
			$tableName,
			$updateData,
			[ 'id' => $id ],
			$updateFormat,
			[ '%d' ]
		);

		if ( $updated !== false ) {
			$this->clearCache();
			return true;
		}

		return false;
	}

	/**
	 * Delete a redirect.
	 *
	 * @param int $id Redirect ID.
	 * @return bool True on success.
	 */
	public function deleteRedirect( int $id ): bool {
		global $wpdb;
		$tableName = RedirectInstaller::getTableName();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Delete operation.
		$deleted = $wpdb->delete(
			$tableName,
			[ 'id' => $id ],
			[ '%d' ]
		);

		if ( $deleted ) {
			$this->clearCache();
			return true;
		}

		return false;
	}

	/**
	 * Validate a regex pattern.
	 *
	 * Checks for syntax validity and potential ReDoS patterns.
	 *
	 * @param string $pattern Pattern to validate.
	 * @return bool True if valid and safe.
	 */
	private function isValidRegex( string $pattern ): bool {
		// Check maximum length to prevent abuse.
		if ( strlen( $pattern ) > 500 ) {
			return false;
		}

		// Check for dangerous nested quantifiers that can cause catastrophic backtracking.
		// Patterns like (a+)+ or (a*)*b or (\w+\s*)+ are ReDoS vectors.
		$dangerousPatterns = [
			'/\([^)]*[+*][^)]*\)[+*]/',    // Nested quantifiers like (a+)+
			'/\([^)]*\|[^)]*\)[+*]/',       // Alternation with outer quantifier like (a|b)+
			'/\.([+*])\1/',                 // Double quantifiers like .**
		];

		foreach ( $dangerousPatterns as $dangerous ) {
			if ( preg_match( $dangerous, $pattern ) ) {
				return false;
			}
		}

		$prepared = $this->prepareRegexPattern( $pattern );

		// Test the pattern with reduced limits.
		$originalLimit = (int) ini_get( 'pcre.backtrack_limit' );
		ini_set( 'pcre.backtrack_limit', '1000' );

		// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Intentional suppression for validation.
		$result = @preg_match( $prepared, '' );

		ini_set( 'pcre.backtrack_limit', (string) $originalLimit );

		return $result !== false;
	}

	/**
	 * Clear redirect caches.
	 */
	private function clearCache(): void {
		\wp_cache_delete( self::CACHE_KEY . '_enabled', 'crispy_seo' );
	}

	/**
	 * Import redirects from CSV.
	 *
	 * @param string $csv    CSV content.
	 * @param bool   $dryRun If true, only validate without importing.
	 * @return array{imported: int, skipped: int, errors: array<string>} Import results.
	 */
	public function importFromCsv( string $csv, bool $dryRun = false ): array {
		$lines    = explode( "\n", trim( $csv ) );
		$imported = 0;
		$skipped  = 0;
		$errors   = [];

		foreach ( $lines as $lineNum => $line ) {
			$line = trim( $line );
			if ( empty( $line ) ) {
				continue;
			}

			// Skip header row if detected.
			if ( $lineNum === 0 && ( stripos( $line, 'source' ) !== false || stripos( $line, 'from' ) !== false ) ) {
				continue;
			}

			$parts = str_getcsv( $line );

			if ( count( $parts ) < 2 ) {
				$errors[] = sprintf(
					/* translators: %d: line number */
					\__( 'Line %d: Invalid format (need at least source and target).', 'crispy-seo' ),
					$lineNum + 1
				);
				++$skipped;
				continue;
			}

			$source    = trim( $parts[0] );
			$target    = trim( $parts[1] );
			$type      = isset( $parts[2] ) ? (int) trim( $parts[2] ) : 301;
			$matchType = isset( $parts[3] ) ? trim( $parts[3] ) : 'exact';

			if ( empty( $source ) || empty( $target ) ) {
				$errors[] = sprintf(
					/* translators: %d: line number */
					\__( 'Line %d: Empty source or target.', 'crispy-seo' ),
					$lineNum + 1
				);
				++$skipped;
				continue;
			}

			if ( ! in_array( $type, self::REDIRECT_TYPES, true ) ) {
				$type = 301;
			}

			if ( ! in_array( $matchType, self::MATCH_TYPES, true ) ) {
				// Auto-detect match type.
				if ( str_starts_with( $source, '^' ) || str_contains( $source, '(' ) ) {
					$matchType = 'regex';
				} elseif ( str_ends_with( $source, '*' ) ) {
					$matchType = 'wildcard';
				} else {
					$matchType = 'exact';
				}
			}

			if ( ! $dryRun ) {
				$result = $this->addRedirect( $source, $target, $type, $matchType );
				if ( $result !== false ) {
					++$imported;
				} else {
					$errors[] = sprintf(
						/* translators: %d: line number, %s: source path */
						\__( 'Line %d: Failed to import %s (may already exist).', 'crispy-seo' ),
						$lineNum + 1,
						$source
					);
					++$skipped;
				}
			} else {
				++$imported;
			}
		}

		return [
			'imported' => $imported,
			'skipped'  => $skipped,
			'errors'   => $errors,
		];
	}

	/**
	 * Export redirects to CSV.
	 *
	 * @param array<string, mixed> $filters Optional filters.
	 * @return string CSV content.
	 */
	public function exportToCsv( array $filters = [] ): string {
		$redirects = $this->getRedirects( $filters );
		$lines     = [];

		// Header row.
		$lines[] = 'source,target,type,match_type,enabled,hits,notes';

		foreach ( $redirects as $redirect ) {
			// Escape fields that might contain commas or quotes.
			$source = $this->escapeCsvField( $redirect['source_path'] );
			$target = $this->escapeCsvField( $redirect['target_url'] );
			$notes  = $this->escapeCsvField( $redirect['notes'] ?? '' );

			$lines[] = sprintf(
				'%s,%s,%d,%s,%d,%d,%s',
				$source,
				$target,
				$redirect['redirect_type'],
				$redirect['match_type'],
				$redirect['enabled'],
				$redirect['hit_count'],
				$notes
			);
		}

		return implode( "\n", $lines );
	}

	/**
	 * Escape a field for CSV output.
	 *
	 * @param string $field Field value.
	 * @return string Escaped field.
	 */
	private function escapeCsvField( string $field ): string {
		if ( str_contains( $field, ',' ) || str_contains( $field, '"' ) || str_contains( $field, "\n" ) ) {
			return '"' . str_replace( '"', '""', $field ) . '"';
		}
		return $field;
	}

	/**
	 * Get redirect statistics.
	 *
	 * @return array<string, int> Statistics.
	 */
	public function getStats(): array {
		global $wpdb;
		$tableName = RedirectInstaller::getTableName();

		if ( ! $this->tableExists() ) {
			return [
				'total'    => 0,
				'enabled'  => 0,
				'disabled' => 0,
				'hits'     => 0,
			];
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Statistics query.
		$result = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT
				COUNT(*) as total,
				SUM(enabled) as enabled,
				SUM(hit_count) as hits
			FROM %i',
				$tableName
			),
			ARRAY_A
		);

		return [
			'total'    => (int) ( $result['total'] ?? 0 ),
			'enabled'  => (int) ( $result['enabled'] ?? 0 ),
			'disabled' => (int) ( $result['total'] ?? 0 ) - (int) ( $result['enabled'] ?? 0 ),
			'hits'     => (int) ( $result['hits'] ?? 0 ),
		];
	}

	/**
	 * AJAX handler: Save redirect.
	 */
	public function ajaxSaveRedirect(): void {
		\check_ajax_referer( 'crispy_seo_admin', 'nonce' );

		if ( ! \current_user_can( 'manage_options' ) ) {
			\wp_send_json_error( [ 'message' => \__( 'Permission denied.', 'crispy-seo' ) ] );
		}

		$source    = isset( $_POST['source'] ) ? \sanitize_text_field( \wp_unslash( $_POST['source'] ) ) : '';
		$target    = isset( $_POST['target'] ) ? esc_url_raw( \wp_unslash( $_POST['target'] ) ) : '';
		$type      = isset( $_POST['type'] ) ? (int) $_POST['type'] : 301;
		$matchType = isset( $_POST['match_type'] ) ? \sanitize_text_field( \wp_unslash( $_POST['match_type'] ) ) : 'exact';
		$notes     = isset( $_POST['notes'] ) ? sanitize_textarea_field( \wp_unslash( $_POST['notes'] ) ) : '';
		$id        = isset( $_POST['id'] ) ? (int) $_POST['id'] : 0;

		if ( empty( $source ) || empty( $target ) ) {
			\wp_send_json_error( [ 'message' => \__( 'Source and target are required.', 'crispy-seo' ) ] );
		}

		if ( $id > 0 ) {
			$result = $this->updateRedirect(
				$id,
				[
					'source_path'   => $source,
					'target_url'    => $target,
					'redirect_type' => $type,
					'match_type'    => $matchType,
					'notes'         => $notes,
				]
			);
		} else {
			$result = $this->addRedirect( $source, $target, $type, $matchType, $notes );
		}

		if ( $result !== false ) {
			\wp_send_json_success( [ 'message' => \__( 'Redirect saved.', 'crispy-seo' ) ] );
		} else {
			\wp_send_json_error( [ 'message' => \__( 'Failed to save redirect. Source may already exist or regex is invalid.', 'crispy-seo' ) ] );
		}
	}

	/**
	 * AJAX handler: Delete redirect.
	 */
	public function ajaxDeleteRedirect(): void {
		\check_ajax_referer( 'crispy_seo_admin', 'nonce' );

		if ( ! \current_user_can( 'manage_options' ) ) {
			\wp_send_json_error( [ 'message' => \__( 'Permission denied.', 'crispy-seo' ) ] );
		}

		$id = isset( $_POST['id'] ) ? (int) $_POST['id'] : 0;

		// Support legacy source-based deletion for backward compatibility.
		if ( $id === 0 && isset( $_POST['source'] ) ) {
			$source    = \sanitize_text_field( \wp_unslash( $_POST['source'] ) );
			$redirects = $this->getRedirects( [ 'search' => $source ] );
			foreach ( $redirects as $redirect ) {
				if ( $redirect['source_path'] === $source ) {
					$id = (int) $redirect['id'];
					break;
				}
			}
		}

		if ( $id === 0 ) {
			\wp_send_json_error( [ 'message' => \__( 'Redirect ID is required.', 'crispy-seo' ) ] );
		}

		if ( $this->deleteRedirect( $id ) ) {
			\wp_send_json_success( [ 'message' => \__( 'Redirect deleted.', 'crispy-seo' ) ] );
		} else {
			\wp_send_json_error( [ 'message' => \__( 'Failed to delete redirect.', 'crispy-seo' ) ] );
		}
	}

	/**
	 * AJAX handler: Import redirects.
	 */
	public function ajaxImportRedirects(): void {
		\check_ajax_referer( 'crispy_seo_admin', 'nonce' );

		if ( ! \current_user_can( 'manage_options' ) ) {
			\wp_send_json_error( [ 'message' => \__( 'Permission denied.', 'crispy-seo' ) ] );
		}

		$csv    = isset( $_POST['csv'] ) ? sanitize_textarea_field( \wp_unslash( $_POST['csv'] ) ) : '';
		$dryRun = isset( $_POST['dry_run'] ) && $_POST['dry_run'] === 'true';

		if ( empty( $csv ) ) {
			\wp_send_json_error( [ 'message' => \__( 'No data provided.', 'crispy-seo' ) ] );
		}

		$result = $this->importFromCsv( $csv, $dryRun );

		$message = sprintf(
			/* translators: 1: number imported, 2: number skipped */
			\__( 'Imported: %1$d, Skipped: %2$d', 'crispy-seo' ),
			$result['imported'],
			$result['skipped']
		);

		if ( $dryRun ) {
			$message = \__( '(Dry run) ', 'crispy-seo' ) . $message;
		}

		\wp_send_json_success(
			[
				'message'  => $message,
				'imported' => $result['imported'],
				'skipped'  => $result['skipped'],
				'errors'   => $result['errors'],
			]
		);
	}

	/**
	 * AJAX handler: Export redirects.
	 */
	public function ajaxExportRedirects(): void {
		\check_ajax_referer( 'crispy_seo_admin', 'nonce' );

		if ( ! \current_user_can( 'manage_options' ) ) {
			\wp_send_json_error( [ 'message' => \__( 'Permission denied.', 'crispy-seo' ) ] );
		}

		$csv = $this->exportToCsv();

		\wp_send_json_success( [ 'csv' => $csv ] );
	}
}
