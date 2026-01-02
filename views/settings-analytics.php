<?php
/**
 * Analytics Settings page.
 *
 * @package CrispySEO
 * @since 1.0.0
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
?>

<div class="wrap">
	<form method="post" action="options.php">
		<?php
		settings_fields( 'crispy_seo_analytics' );
		?>

		<p class="description" style="margin-bottom: 20px;">
			<?php esc_html_e( 'Configure one or more analytics providers. Scripts are added to the site footer.', 'crispy-seo' ); ?>
		</p>

		<h2><?php esc_html_e( 'Google Analytics 4', 'crispy-seo' ); ?></h2>

		<table class="form-table">
			<tr>
				<th scope="row">
					<label for="crispy_seo_ga4_id"><?php esc_html_e( 'Measurement ID', 'crispy-seo' ); ?></label>
				</th>
				<td>
					<input type="text" id="crispy_seo_ga4_id" name="crispy_seo_ga4_id"
						   value="<?php echo esc_attr( get_option( 'crispy_seo_ga4_id', '' ) ); ?>"
						   class="regular-text" placeholder="G-XXXXXXXXXX">
					<p class="description">
						<?php esc_html_e( 'Your GA4 Measurement ID (starts with G-).', 'crispy-seo' ); ?>
					</p>
				</td>
			</tr>
		</table>

		<h2><?php esc_html_e( 'Plausible Analytics', 'crispy-seo' ); ?></h2>

		<table class="form-table">
			<tr>
				<th scope="row">
					<label for="crispy_seo_plausible_domain"><?php esc_html_e( 'Domain', 'crispy-seo' ); ?></label>
				</th>
				<td>
					<input type="text" id="crispy_seo_plausible_domain" name="crispy_seo_plausible_domain"
						   value="<?php echo esc_attr( get_option( 'crispy_seo_plausible_domain', '' ) ); ?>"
						   class="regular-text" placeholder="example.com">
					<p class="description">
						<?php esc_html_e( 'Your domain as configured in Plausible (without https://).', 'crispy-seo' ); ?>
					</p>
				</td>
			</tr>
		</table>

		<h2><?php esc_html_e( 'Fathom Analytics', 'crispy-seo' ); ?></h2>

		<table class="form-table">
			<tr>
				<th scope="row">
					<label for="crispy_seo_fathom_site_id"><?php esc_html_e( 'Site ID', 'crispy-seo' ); ?></label>
				</th>
				<td>
					<input type="text" id="crispy_seo_fathom_site_id" name="crispy_seo_fathom_site_id"
						   value="<?php echo esc_attr( get_option( 'crispy_seo_fathom_site_id', '' ) ); ?>"
						   class="regular-text" placeholder="ABCDEFGH">
					<p class="description">
						<?php esc_html_e( 'Your Fathom Site ID.', 'crispy-seo' ); ?>
					</p>
				</td>
			</tr>
		</table>

		<h2><?php esc_html_e( 'Matomo Analytics', 'crispy-seo' ); ?></h2>

		<table class="form-table">
			<tr>
				<th scope="row">
					<label for="crispy_seo_matomo_url"><?php esc_html_e( 'Matomo URL', 'crispy-seo' ); ?></label>
				</th>
				<td>
					<input type="url" id="crispy_seo_matomo_url" name="crispy_seo_matomo_url"
						   value="<?php echo esc_url( get_option( 'crispy_seo_matomo_url', '' ) ); ?>"
						   class="large-text" placeholder="https://analytics.example.com/">
					<p class="description">
						<?php esc_html_e( 'Your Matomo instance URL (with trailing slash).', 'crispy-seo' ); ?>
					</p>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="crispy_seo_matomo_site_id"><?php esc_html_e( 'Site ID', 'crispy-seo' ); ?></label>
				</th>
				<td>
					<input type="number" id="crispy_seo_matomo_site_id" name="crispy_seo_matomo_site_id"
						   value="<?php echo esc_attr( get_option( 'crispy_seo_matomo_site_id', '' ) ); ?>"
						   class="small-text" min="1">
					<p class="description">
						<?php esc_html_e( 'Your Matomo Site ID (numeric).', 'crispy-seo' ); ?>
					</p>
				</td>
			</tr>
		</table>

		<?php submit_button(); ?>
	</form>
</div>
