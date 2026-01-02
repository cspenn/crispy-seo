<?php
/**
 * Tools settings page.
 *
 * @package CrispySEO
 * @since 2.0.0
 */

declare(strict_types=1);

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Verify capability.
if ( ! current_user_can( 'manage_options' ) ) {
	wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'crispy-seo' ) );
}

$active_tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'search-replace'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
?>

<div class="wrap crispy-seo-tools">
	<h1><?php esc_html_e( 'CrispySEO Tools', 'crispy-seo' ); ?></h1>

	<nav class="nav-tab-wrapper">
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=crispy-seo-tools&tab=search-replace' ) ); ?>"
		   class="nav-tab <?php echo $active_tab === 'search-replace' ? 'nav-tab-active' : ''; ?>">
			<?php esc_html_e( 'Search & Replace', 'crispy-seo' ); ?>
		</a>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=crispy-seo-tools&tab=import-export' ) ); ?>"
		   class="nav-tab <?php echo $active_tab === 'import-export' ? 'nav-tab-active' : ''; ?>">
			<?php esc_html_e( 'Import / Export', 'crispy-seo' ); ?>
		</a>
	</nav>

	<div class="tab-content" style="margin-top: 20px;">
		<?php if ( $active_tab === 'search-replace' ) : ?>
			<?php include CRISPY_SEO_DIR . 'views/tools-search-replace.php'; ?>
		<?php elseif ( $active_tab === 'import-export' ) : ?>
			<div class="card">
				<h2><?php esc_html_e( 'Import / Export SEO Data', 'crispy-seo' ); ?></h2>
				<p><?php esc_html_e( 'Import and export functionality coming soon.', 'crispy-seo' ); ?></p>
			</div>
		<?php endif; ?>
	</div>
</div>
