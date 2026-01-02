<?php
/**
 * Social Settings page.
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
		settings_fields( 'crispy_seo_social' );
		?>

		<h2><?php esc_html_e( 'Open Graph', 'crispy-seo' ); ?></h2>

		<table class="form-table">
			<tr>
				<th scope="row">
					<label for="crispy_seo_og_default_image"><?php esc_html_e( 'Default OG Image', 'crispy-seo' ); ?></label>
				</th>
				<td>
					<input type="url" id="crispy_seo_og_default_image" name="crispy_seo_og_default_image"
						   value="<?php echo esc_url( get_option( 'crispy_seo_og_default_image', '' ) ); ?>"
						   class="large-text">
					<p class="description">
						<?php esc_html_e( 'Default image for social sharing when no featured image is set. Recommended: 1200x630 pixels.', 'crispy-seo' ); ?>
					</p>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="crispy_seo_facebook_app_id"><?php esc_html_e( 'Facebook App ID', 'crispy-seo' ); ?></label>
				</th>
				<td>
					<input type="text" id="crispy_seo_facebook_app_id" name="crispy_seo_facebook_app_id"
						   value="<?php echo esc_attr( get_option( 'crispy_seo_facebook_app_id', '' ) ); ?>"
						   class="regular-text">
					<p class="description">
						<?php esc_html_e( 'Optional. Your Facebook App ID for insights.', 'crispy-seo' ); ?>
					</p>
				</td>
			</tr>
		</table>

		<h2><?php esc_html_e( 'Twitter Cards', 'crispy-seo' ); ?></h2>

		<table class="form-table">
			<tr>
				<th scope="row">
					<label for="crispy_seo_twitter_card_type"><?php esc_html_e( 'Card Type', 'crispy-seo' ); ?></label>
				</th>
				<td>
					<select id="crispy_seo_twitter_card_type" name="crispy_seo_twitter_card_type">
						<option value="summary" <?php selected( get_option( 'crispy_seo_twitter_card_type', 'summary_large_image' ), 'summary' ); ?>>
							<?php esc_html_e( 'Summary', 'crispy-seo' ); ?>
						</option>
						<option value="summary_large_image" <?php selected( get_option( 'crispy_seo_twitter_card_type', 'summary_large_image' ), 'summary_large_image' ); ?>>
							<?php esc_html_e( 'Summary with Large Image', 'crispy-seo' ); ?>
						</option>
					</select>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="crispy_seo_twitter_site"><?php esc_html_e( 'Twitter Username', 'crispy-seo' ); ?></label>
				</th>
				<td>
					<input type="text" id="crispy_seo_twitter_site" name="crispy_seo_twitter_site"
						   value="<?php echo esc_attr( get_option( 'crispy_seo_twitter_site', '' ) ); ?>"
						   class="regular-text" placeholder="@username">
					<p class="description">
						<?php esc_html_e( 'Your Twitter/X username including the @ symbol.', 'crispy-seo' ); ?>
					</p>
				</td>
			</tr>
		</table>

		<?php submit_button(); ?>
	</form>
</div>
