<?php
/**
 * Schema Settings page.
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
		settings_fields( 'crispy_seo_schema' );
		?>

		<h2><?php esc_html_e( 'Organization Schema', 'crispy-seo' ); ?></h2>

		<table class="form-table">
			<tr>
				<th scope="row">
					<label for="crispy_seo_organization_name"><?php esc_html_e( 'Organization Name', 'crispy-seo' ); ?></label>
				</th>
				<td>
					<input type="text" id="crispy_seo_organization_name" name="crispy_seo_organization_name"
						   value="<?php echo esc_attr( get_option( 'crispy_seo_organization_name', get_bloginfo( 'name' ) ) ); ?>"
						   class="regular-text">
					<p class="description">
						<?php esc_html_e( 'Your organization or brand name for structured data.', 'crispy-seo' ); ?>
					</p>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="crispy_seo_organization_logo"><?php esc_html_e( 'Organization Logo', 'crispy-seo' ); ?></label>
				</th>
				<td>
					<input type="url" id="crispy_seo_organization_logo" name="crispy_seo_organization_logo"
						   value="<?php echo esc_url( get_option( 'crispy_seo_organization_logo', '' ) ); ?>"
						   class="large-text">
					<p class="description">
						<?php esc_html_e( 'URL to your organization logo. Recommended: Square image, minimum 112x112 pixels.', 'crispy-seo' ); ?>
					</p>
				</td>
			</tr>
		</table>

		<h2><?php esc_html_e( 'Default Schema', 'crispy-seo' ); ?></h2>

		<table class="form-table">
			<tr>
				<th scope="row">
					<label for="crispy_seo_default_schema_type"><?php esc_html_e( 'Default Article Type', 'crispy-seo' ); ?></label>
				</th>
				<td>
					<select id="crispy_seo_default_schema_type" name="crispy_seo_default_schema_type">
						<option value="Article" <?php selected( get_option( 'crispy_seo_default_schema_type', 'Article' ), 'Article' ); ?>>
							<?php esc_html_e( 'Article', 'crispy-seo' ); ?>
						</option>
						<option value="BlogPosting" <?php selected( get_option( 'crispy_seo_default_schema_type', 'Article' ), 'BlogPosting' ); ?>>
							<?php esc_html_e( 'Blog Posting', 'crispy-seo' ); ?>
						</option>
						<option value="NewsArticle" <?php selected( get_option( 'crispy_seo_default_schema_type', 'Article' ), 'NewsArticle' ); ?>>
							<?php esc_html_e( 'News Article', 'crispy-seo' ); ?>
						</option>
						<option value="TechArticle" <?php selected( get_option( 'crispy_seo_default_schema_type', 'Article' ), 'TechArticle' ); ?>>
							<?php esc_html_e( 'Tech Article', 'crispy-seo' ); ?>
						</option>
					</select>
					<p class="description">
						<?php esc_html_e( 'Default schema type for posts. Can be overridden per post.', 'crispy-seo' ); ?>
					</p>
				</td>
			</tr>
		</table>

		<?php submit_button(); ?>
	</form>
</div>
