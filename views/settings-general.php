<?php
/**
 * General Settings page.
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
		settings_fields( 'crispy_seo_general' );
		?>

		<table class="form-table">
			<tr>
				<th scope="row">
					<label for="crispy_seo_title_separator"><?php esc_html_e( 'Title Separator', 'crispy-seo' ); ?></label>
				</th>
				<td>
					<input type="text" id="crispy_seo_title_separator" name="crispy_seo_title_separator"
						   value="<?php echo esc_attr( get_option( 'crispy_seo_title_separator', '|' ) ); ?>"
						   class="regular-text" maxlength="10">
					<p class="description">
						<?php esc_html_e( 'Character(s) used to separate title parts (e.g., Page Title | Site Name).', 'crispy-seo' ); ?>
					</p>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="crispy_seo_homepage_title"><?php esc_html_e( 'Homepage Title', 'crispy-seo' ); ?></label>
				</th>
				<td>
					<input type="text" id="crispy_seo_homepage_title" name="crispy_seo_homepage_title"
						   value="<?php echo esc_attr( get_option( 'crispy_seo_homepage_title', '' ) ); ?>"
						   class="large-text">
					<p class="description">
						<?php esc_html_e( 'Custom title for the homepage. Leave empty to use site title.', 'crispy-seo' ); ?>
					</p>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="crispy_seo_homepage_description"><?php esc_html_e( 'Homepage Description', 'crispy-seo' ); ?></label>
				</th>
				<td>
					<textarea id="crispy_seo_homepage_description" name="crispy_seo_homepage_description"
							  rows="3" class="large-text"><?php echo esc_textarea( get_option( 'crispy_seo_homepage_description', '' ) ); ?></textarea>
					<p class="description">
						<?php esc_html_e( 'Meta description for the homepage. Recommended: 150-160 characters.', 'crispy-seo' ); ?>
					</p>
				</td>
			</tr>
		</table>

		<?php submit_button(); ?>
	</form>
</div>
