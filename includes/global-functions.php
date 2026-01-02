<?php
/**
 * Global function wrappers for CrispySEO.
 *
 * This file is NOT namespaced, providing global access to plugin functions.
 *
 * @package CrispySEO
 * @since 2.0.0
 */

declare(strict_types=1);

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'crispy_seo' ) ) {
	/**
	 * Get the CrispySEO plugin instance.
	 *
	 * Global wrapper for the namespaced function.
	 *
	 * @return \CrispySEO\CrispySEO Plugin instance.
	 */
	function crispy_seo(): \CrispySEO\CrispySEO {
		return \CrispySEO\CrispySEO::getInstance();
	}
}
