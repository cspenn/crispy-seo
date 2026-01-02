<?php
/**
 * Title Templates Settings page.
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

$default_template = '%title% %sep% %sitename%';
?>

<div class="wrap">
	<form method="post" action="options.php">
		<?php
		settings_fields( 'crispy_seo_titles' );
		?>

		<p class="description" style="margin-bottom: 20px;">
			<?php esc_html_e( 'Available variables: %title%, %sitename%, %sep%, %excerpt%, %date%, %author%', 'crispy-seo' ); ?>
		</p>

		<table class="form-table">
			<tr>
				<th scope="row">
					<label for="crispy_seo_title_template_post"><?php esc_html_e( 'Post Title Template', 'crispy-seo' ); ?></label>
				</th>
				<td>
					<input type="text" id="crispy_seo_title_template_post" name="crispy_seo_title_template_post"
						   value="<?php echo esc_attr( get_option( 'crispy_seo_title_template_post', $default_template ) ); ?>"
						   class="large-text">
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="crispy_seo_title_template_page"><?php esc_html_e( 'Page Title Template', 'crispy-seo' ); ?></label>
				</th>
				<td>
					<input type="text" id="crispy_seo_title_template_page" name="crispy_seo_title_template_page"
						   value="<?php echo esc_attr( get_option( 'crispy_seo_title_template_page', $default_template ) ); ?>"
						   class="large-text">
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="crispy_seo_title_template_archive"><?php esc_html_e( 'Archive Title Template', 'crispy-seo' ); ?></label>
				</th>
				<td>
					<input type="text" id="crispy_seo_title_template_archive" name="crispy_seo_title_template_archive"
						   value="<?php echo esc_attr( get_option( 'crispy_seo_title_template_archive', $default_template ) ); ?>"
						   class="large-text">
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="crispy_seo_title_template_author"><?php esc_html_e( 'Author Title Template', 'crispy-seo' ); ?></label>
				</th>
				<td>
					<input type="text" id="crispy_seo_title_template_author" name="crispy_seo_title_template_author"
						   value="<?php echo esc_attr( get_option( 'crispy_seo_title_template_author', '%author% %sep% %sitename%' ) ); ?>"
						   class="large-text">
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="crispy_seo_title_template_search"><?php esc_html_e( 'Search Title Template', 'crispy-seo' ); ?></label>
				</th>
				<td>
					<input type="text" id="crispy_seo_title_template_search" name="crispy_seo_title_template_search"
						   value="<?php echo esc_attr( get_option( 'crispy_seo_title_template_search', 'Search: %search% %sep% %sitename%' ) ); ?>"
						   class="large-text">
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="crispy_seo_title_template_404"><?php esc_html_e( '404 Title Template', 'crispy-seo' ); ?></label>
				</th>
				<td>
					<input type="text" id="crispy_seo_title_template_404" name="crispy_seo_title_template_404"
						   value="<?php echo esc_attr( get_option( 'crispy_seo_title_template_404', 'Page Not Found %sep% %sitename%' ) ); ?>"
						   class="large-text">
				</td>
			</tr>
		</table>

		<?php submit_button(); ?>
	</form>
</div>
