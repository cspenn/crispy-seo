<?php
/**
 * Redirect Manager
 *
 * @package CrispySEO\Technical
 */

declare(strict_types=1);

namespace CrispySEO\Technical;

/**
 * Manages 301/302 redirects.
 */
class Redirects {

	/**
	 * Option key for storing redirects.
	 */
	public const OPTION_KEY = 'crispy_seo_redirects';

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'template_redirect', [ $this, 'handleRedirect' ], 1 );
		add_action( 'wp_ajax_crispy_seo_save_redirect', [ $this, 'ajaxSaveRedirect' ] );
		add_action( 'wp_ajax_crispy_seo_delete_redirect', [ $this, 'ajaxDeleteRedirect' ] );
		add_action( 'wp_ajax_crispy_seo_import_redirects', [ $this, 'ajaxImportRedirects' ] );
	}

	/**
	 * Handle redirect if URL matches.
	 */
	public function handleRedirect(): void {
		if ( is_admin() ) {
			return;
		}

		$redirects   = $this->getRedirects();
		$currentPath = $this->getCurrentPath();

		foreach ( $redirects as $redirect ) {
			if ( $this->matchesPath( $currentPath, $redirect['source'] ) ) {
				$target = $redirect['target'];
				$type   = (int) ( $redirect['type'] ?? 301 );

				// Handle relative targets.
				if ( strpos( $target, '/' ) === 0 ) {
					$target = home_url( $target );
				}

				wp_redirect( $target, $type );
				exit;
			}
		}
	}

	/**
	 * Get current request path.
	 */
	private function getCurrentPath(): string {
		$path = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( $_SERVER['REQUEST_URI'] ) : '/';

		// Parse URL to get just the path.
		$parsed = wp_parse_url( $path );

		return $parsed['path'] ?? '/';
	}

	/**
	 * Check if path matches redirect source.
	 *
	 * @param string $path   Current path.
	 * @param string $source Redirect source pattern.
	 */
	private function matchesPath( string $path, string $source ): bool {
		// Exact match.
		if ( $path === $source ) {
			return true;
		}

		// Trailing slash normalization.
		$normalizedPath   = rtrim( $path, '/' );
		$normalizedSource = rtrim( $source, '/' );

		if ( $normalizedPath === $normalizedSource ) {
			return true;
		}

		// Wildcard matching (source ends with *).
		if ( substr( $source, -1 ) === '*' ) {
			$prefix = rtrim( $source, '*' );
			return strpos( $path, $prefix ) === 0;
		}

		// Regex matching (source starts with ^).
		if ( strpos( $source, '^' ) === 0 ) {
			return (bool) preg_match( '#' . $source . '#', $path );
		}

		return false;
	}

	/**
	 * Get all redirects.
	 *
	 * @return array<array{source: string, target: string, type: int}>
	 */
	public function getRedirects(): array {
		$redirects = get_option( self::OPTION_KEY, [] );

		if ( ! is_array( $redirects ) ) {
			return [];
		}

		return array_map(
			function ( $redirect ) {
				return [
					'source'  => $redirect['source'] ?? '',
					'target'  => $redirect['target'] ?? '',
					'type'    => (int) ( $redirect['type'] ?? 301 ),
					'hits'    => (int) ( $redirect['hits'] ?? 0 ),
					'created' => $redirect['created'] ?? current_time( 'mysql' ),
				];
			},
			$redirects
		);
	}

	/**
	 * Add a redirect.
	 *
	 * @param string $source Source URL/path.
	 * @param string $target Target URL.
	 * @param int    $type   Redirect type (301 or 302).
	 */
	public function addRedirect( string $source, string $target, int $type = 301 ): bool {
		$redirects = $this->getRedirects();

		// Check for duplicate.
		foreach ( $redirects as $redirect ) {
			if ( $redirect['source'] === $source ) {
				return false;
			}
		}

		// Validate type.
		if ( ! in_array( $type, [ 301, 302, 307, 308 ], true ) ) {
			$type = 301;
		}

		$redirects[] = [
			'source'  => $source,
			'target'  => $target,
			'type'    => $type,
			'hits'    => 0,
			'created' => current_time( 'mysql' ),
		];

		return update_option( self::OPTION_KEY, $redirects );
	}

	/**
	 * Update a redirect.
	 *
	 * @param string $oldSource Original source URL.
	 * @param string $source    New source URL.
	 * @param string $target    Target URL.
	 * @param int    $type      Redirect type.
	 */
	public function updateRedirect( string $oldSource, string $source, string $target, int $type = 301 ): bool {
		$redirects = $this->getRedirects();

		foreach ( $redirects as $index => $redirect ) {
			if ( $redirect['source'] === $oldSource ) {
				$redirects[ $index ]['source'] = $source;
				$redirects[ $index ]['target'] = $target;
				$redirects[ $index ]['type']   = $type;

				return update_option( self::OPTION_KEY, $redirects );
			}
		}

		return false;
	}

	/**
	 * Delete a redirect.
	 *
	 * @param string $source Source URL to delete.
	 */
	public function deleteRedirect( string $source ): bool {
		$redirects = $this->getRedirects();
		$filtered  = array_filter( $redirects, fn( $r ) => $r['source'] !== $source );

		if ( count( $filtered ) === count( $redirects ) ) {
			return false;
		}

		return update_option( self::OPTION_KEY, array_values( $filtered ) );
	}

	/**
	 * Import redirects from CSV.
	 *
	 * @param string $csv CSV content.
	 * @return int Number of imported redirects.
	 */
	public function importFromCsv( string $csv ): int {
		$lines    = explode( "\n", trim( $csv ) );
		$imported = 0;

		foreach ( $lines as $line ) {
			$parts = str_getcsv( $line );

			if ( count( $parts ) < 2 ) {
				continue;
			}

			$source = trim( $parts[0] );
			$target = trim( $parts[1] );
			$type   = isset( $parts[2] ) ? (int) trim( $parts[2] ) : 301;

			if ( ! empty( $source ) && ! empty( $target ) ) {
				if ( $this->addRedirect( $source, $target, $type ) ) {
					++$imported;
				}
			}
		}

		return $imported;
	}

	/**
	 * Export redirects to CSV.
	 */
	public function exportToCsv(): string {
		$redirects = $this->getRedirects();
		$lines     = [];

		foreach ( $redirects as $redirect ) {
			$lines[] = sprintf(
				'%s,%s,%d',
				$redirect['source'],
				$redirect['target'],
				$redirect['type']
			);
		}

		return implode( "\n", $lines );
	}

	/**
	 * AJAX handler: Save redirect.
	 */
	public function ajaxSaveRedirect(): void {
		check_ajax_referer( 'crispy_seo_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'Permission denied.', 'crispy-seo' ) ] );
		}

		$source    = isset( $_POST['source'] ) ? sanitize_text_field( $_POST['source'] ) : '';
		$target    = isset( $_POST['target'] ) ? esc_url_raw( $_POST['target'] ) : '';
		$type      = isset( $_POST['type'] ) ? (int) $_POST['type'] : 301;
		$oldSource = isset( $_POST['old_source'] ) ? sanitize_text_field( $_POST['old_source'] ) : '';

		if ( empty( $source ) || empty( $target ) ) {
			wp_send_json_error( [ 'message' => __( 'Source and target are required.', 'crispy-seo' ) ] );
		}

		if ( ! empty( $oldSource ) ) {
			$result = $this->updateRedirect( $oldSource, $source, $target, $type );
		} else {
			$result = $this->addRedirect( $source, $target, $type );
		}

		if ( $result ) {
			wp_send_json_success( [ 'message' => __( 'Redirect saved.', 'crispy-seo' ) ] );
		} else {
			wp_send_json_error( [ 'message' => __( 'Failed to save redirect.', 'crispy-seo' ) ] );
		}
	}

	/**
	 * AJAX handler: Delete redirect.
	 */
	public function ajaxDeleteRedirect(): void {
		check_ajax_referer( 'crispy_seo_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'Permission denied.', 'crispy-seo' ) ] );
		}

		$source = isset( $_POST['source'] ) ? sanitize_text_field( $_POST['source'] ) : '';

		if ( empty( $source ) ) {
			wp_send_json_error( [ 'message' => __( 'Source is required.', 'crispy-seo' ) ] );
		}

		if ( $this->deleteRedirect( $source ) ) {
			wp_send_json_success( [ 'message' => __( 'Redirect deleted.', 'crispy-seo' ) ] );
		} else {
			wp_send_json_error( [ 'message' => __( 'Failed to delete redirect.', 'crispy-seo' ) ] );
		}
	}

	/**
	 * AJAX handler: Import redirects.
	 */
	public function ajaxImportRedirects(): void {
		check_ajax_referer( 'crispy_seo_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'Permission denied.', 'crispy-seo' ) ] );
		}

		$csv = isset( $_POST['csv'] ) ? sanitize_textarea_field( $_POST['csv'] ) : '';

		if ( empty( $csv ) ) {
			wp_send_json_error( [ 'message' => __( 'No data provided.', 'crispy-seo' ) ] );
		}

		$count = $this->importFromCsv( $csv );

		wp_send_json_success(
			[
				'message' => sprintf(
					/* translators: %d: number of redirects */
					__( 'Imported %d redirects.', 'crispy-seo' ),
					$count
				),
			]
		);
	}
}
