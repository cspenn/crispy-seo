<?php
/**
 * Sitemap Settings page.
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

$post_types      = get_post_types( [ 'public' => true ], 'objects' );
$taxonomies      = get_taxonomies( [ 'public' => true ], 'objects' );
$enabled_types   = get_option( 'crispy_seo_sitemap_post_types', [ 'post', 'page' ] );
$enabled_taxos   = get_option( 'crispy_seo_sitemap_taxonomies', [ 'category', 'post_tag' ] );
$sitemap_enabled = get_option( 'crispy_seo_sitemap_enabled', true );

if ( ! is_array( $enabled_types ) ) {
	$enabled_types = [ 'post', 'page' ];
}
if ( ! is_array( $enabled_taxos ) ) {
	$enabled_taxos = [ 'category', 'post_tag' ];
}
?>

<div class="wrap">
	<form method="post" action="options.php">
		<?php
		settings_fields( 'crispy_seo_sitemap' );
		?>

		<table class="form-table">
			<tr>
				<th scope="row"><?php esc_html_e( 'Enable XML Sitemap', 'crispy-seo' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="crispy_seo_sitemap_enabled" value="1"
							<?php checked( $sitemap_enabled ); ?>>
						<?php esc_html_e( 'Generate XML sitemap', 'crispy-seo' ); ?>
					</label>
					<p class="description">
						<?php
						printf(
							/* translators: %s: sitemap URL */
							esc_html__( 'Sitemap URL: %s', 'crispy-seo' ),
							'<code>' . esc_url( home_url( '/sitemap.xml' ) ) . '</code>'
						);
						?>
					</p>
				</td>
			</tr>
		</table>

		<h2><?php esc_html_e( 'Post Types', 'crispy-seo' ); ?></h2>

		<table class="form-table">
			<tr>
				<th scope="row"><?php esc_html_e( 'Include in Sitemap', 'crispy-seo' ); ?></th>
				<td>
					<?php foreach ( $post_types as $post_type ) : ?>
						<?php if ( 'attachment' === $post_type->name ) continue; ?>
						<label style="display: block; margin-bottom: 8px;">
							<input type="checkbox" name="crispy_seo_sitemap_post_types[]"
								   value="<?php echo esc_attr( $post_type->name ); ?>"
								<?php checked( in_array( $post_type->name, $enabled_types, true ) ); ?>>
							<?php echo esc_html( $post_type->label ); ?>
						</label>
					<?php endforeach; ?>
				</td>
			</tr>
		</table>

		<h2><?php esc_html_e( 'Taxonomies', 'crispy-seo' ); ?></h2>

		<table class="form-table">
			<tr>
				<th scope="row"><?php esc_html_e( 'Include in Sitemap', 'crispy-seo' ); ?></th>
				<td>
					<?php foreach ( $taxonomies as $taxonomy ) : ?>
						<label style="display: block; margin-bottom: 8px;">
							<input type="checkbox" name="crispy_seo_sitemap_taxonomies[]"
								   value="<?php echo esc_attr( $taxonomy->name ); ?>"
								<?php checked( in_array( $taxonomy->name, $enabled_taxos, true ) ); ?>>
							<?php echo esc_html( $taxonomy->label ); ?>
						</label>
					<?php endforeach; ?>
				</td>
			</tr>
		</table>

		<?php submit_button(); ?>
	</form>
</div>
