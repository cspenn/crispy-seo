<?php
/**
 * SEO Meta Box Template
 *
 * @package CrispySEO
 * @var \WP_Post $post
 * @var string $metaTitle
 * @var string $metaDescription
 * @var string $focusKeyword
 * @var string $schemaType
 * @var string $canonical
 * @var string $noindex
 */

defined( 'ABSPATH' ) || exit;

$schemaTypes = \CrispySEO\Schema\SchemaFactory::SCHEMA_TYPES;
?>

<div class="crispy-seo-metabox">
	<div class="crispy-seo-tabs">
		<button type="button" class="crispy-seo-tab crispy-seo-tab--active" data-tab="general">
			<?php esc_html_e( 'General', 'crispy-seo' ); ?>
		</button>
		<button type="button" class="crispy-seo-tab" data-tab="social">
			<?php esc_html_e( 'Social', 'crispy-seo' ); ?>
		</button>
		<button type="button" class="crispy-seo-tab" data-tab="schema">
			<?php esc_html_e( 'Schema', 'crispy-seo' ); ?>
		</button>
		<button type="button" class="crispy-seo-tab" data-tab="advanced">
			<?php esc_html_e( 'Advanced', 'crispy-seo' ); ?>
		</button>
	</div>

	<!-- General Tab -->
	<div class="crispy-seo-panel crispy-seo-panel--active" data-panel="general">
		<div class="crispy-seo-field">
			<label for="crispy_seo_focus_keyword">
				<?php esc_html_e( 'Focus Keyword', 'crispy-seo' ); ?>
			</label>
			<input type="text"
					id="crispy_seo_focus_keyword"
					name="crispy_seo_focus_keyword"
					value="<?php echo esc_attr( $focusKeyword ); ?>"
					class="large-text"
					placeholder="<?php esc_attr_e( 'Enter your focus keyword', 'crispy-seo' ); ?>">
			<p class="description">
				<?php esc_html_e( 'The primary keyword you want this page to rank for.', 'crispy-seo' ); ?>
			</p>
		</div>

		<div class="crispy-seo-field">
			<label for="crispy_seo_title">
				<?php esc_html_e( 'SEO Title', 'crispy-seo' ); ?>
			</label>
			<input type="text"
					id="crispy_seo_title"
					name="crispy_seo_title"
					value="<?php echo esc_attr( $metaTitle ); ?>"
					class="large-text"
					maxlength="70"
					placeholder="<?php echo esc_attr( $post->post_title ); ?>">
			<div class="crispy-seo-counter">
				<span class="crispy-seo-counter__current">0</span> / 70
			</div>
			<p class="description">
				<?php esc_html_e( 'The title that will appear in search results. Leave empty to use the default.', 'crispy-seo' ); ?>
			</p>
		</div>

		<div class="crispy-seo-field">
			<label for="crispy_seo_description">
				<?php esc_html_e( 'Meta Description', 'crispy-seo' ); ?>
			</label>
			<textarea id="crispy_seo_description"
						name="crispy_seo_description"
						class="large-text"
						rows="3"
						maxlength="160"
						placeholder="<?php esc_attr_e( 'Enter a description for search engines...', 'crispy-seo' ); ?>"><?php echo esc_textarea( $metaDescription ); ?></textarea>
			<div class="crispy-seo-counter">
				<span class="crispy-seo-counter__current">0</span> / 160
			</div>
			<p class="description">
				<?php esc_html_e( 'The description that will appear in search results. Leave empty to auto-generate.', 'crispy-seo' ); ?>
			</p>
		</div>

		<div class="crispy-seo-preview">
			<h4><?php esc_html_e( 'Search Preview', 'crispy-seo' ); ?></h4>
			<div class="crispy-seo-preview__box">
				<div class="crispy-seo-preview__title"></div>
				<div class="crispy-seo-preview__url"><?php echo esc_url( get_permalink( $post->ID ) ); ?></div>
				<div class="crispy-seo-preview__description"></div>
			</div>
		</div>
	</div>

	<!-- Social Tab -->
	<div class="crispy-seo-panel" data-panel="social">
		<h4><?php esc_html_e( 'Open Graph (Facebook, LinkedIn)', 'crispy-seo' ); ?></h4>

		<div class="crispy-seo-field">
			<label for="crispy_seo_og_title">
				<?php esc_html_e( 'OG Title', 'crispy-seo' ); ?>
			</label>
			<input type="text"
					id="crispy_seo_og_title"
					name="crispy_seo_og_title"
					value="<?php echo esc_attr( get_post_meta( $post->ID, '_crispy_seo_og_title', true ) ); ?>"
					class="large-text"
					placeholder="<?php esc_attr_e( 'Uses SEO title if empty', 'crispy-seo' ); ?>">
		</div>

		<div class="crispy-seo-field">
			<label for="crispy_seo_og_description">
				<?php esc_html_e( 'OG Description', 'crispy-seo' ); ?>
			</label>
			<textarea id="crispy_seo_og_description"
						name="crispy_seo_og_description"
						class="large-text"
						rows="2"
						placeholder="<?php esc_attr_e( 'Uses meta description if empty', 'crispy-seo' ); ?>"><?php echo esc_textarea( get_post_meta( $post->ID, '_crispy_seo_og_description', true ) ); ?></textarea>
		</div>

		<div class="crispy-seo-field">
			<label for="crispy_seo_og_image">
				<?php esc_html_e( 'OG Image URL', 'crispy-seo' ); ?>
			</label>
			<input type="url"
					id="crispy_seo_og_image"
					name="crispy_seo_og_image"
					value="<?php echo esc_url( get_post_meta( $post->ID, '_crispy_seo_og_image', true ) ); ?>"
					class="large-text"
					placeholder="<?php esc_attr_e( 'Uses featured image if empty', 'crispy-seo' ); ?>">
		</div>

		<hr>

		<h4><?php esc_html_e( 'Twitter Card', 'crispy-seo' ); ?></h4>

		<div class="crispy-seo-field">
			<label for="crispy_seo_twitter_title">
				<?php esc_html_e( 'Twitter Title', 'crispy-seo' ); ?>
			</label>
			<input type="text"
					id="crispy_seo_twitter_title"
					name="crispy_seo_twitter_title"
					value="<?php echo esc_attr( get_post_meta( $post->ID, '_crispy_seo_twitter_title', true ) ); ?>"
					class="large-text"
					placeholder="<?php esc_attr_e( 'Uses OG title if empty', 'crispy-seo' ); ?>">
		</div>

		<div class="crispy-seo-field">
			<label for="crispy_seo_twitter_description">
				<?php esc_html_e( 'Twitter Description', 'crispy-seo' ); ?>
			</label>
			<textarea id="crispy_seo_twitter_description"
						name="crispy_seo_twitter_description"
						class="large-text"
						rows="2"
						placeholder="<?php esc_attr_e( 'Uses OG description if empty', 'crispy-seo' ); ?>"><?php echo esc_textarea( get_post_meta( $post->ID, '_crispy_seo_twitter_description', true ) ); ?></textarea>
		</div>

		<div class="crispy-seo-field">
			<label for="crispy_seo_twitter_image">
				<?php esc_html_e( 'Twitter Image URL', 'crispy-seo' ); ?>
			</label>
			<input type="url"
					id="crispy_seo_twitter_image"
					name="crispy_seo_twitter_image"
					value="<?php echo esc_url( get_post_meta( $post->ID, '_crispy_seo_twitter_image', true ) ); ?>"
					class="large-text"
					placeholder="<?php esc_attr_e( 'Uses OG image if empty', 'crispy-seo' ); ?>">
		</div>
	</div>

	<!-- Schema Tab -->
	<div class="crispy-seo-panel" data-panel="schema">
		<div class="crispy-seo-field">
			<label for="crispy_seo_schema_type">
				<?php esc_html_e( 'Schema Type', 'crispy-seo' ); ?>
			</label>
			<select id="crispy_seo_schema_type"
					name="crispy_seo_schema_type"
					class="regular-text">
				<option value=""><?php esc_html_e( 'Default (Article)', 'crispy-seo' ); ?></option>
				<?php foreach ( $schemaTypes as $type ) : ?>
					<option value="<?php echo esc_attr( $type ); ?>" <?php selected( $schemaType, $type ); ?>>
						<?php echo esc_html( $type ); ?>
					</option>
				<?php endforeach; ?>
			</select>
			<p class="description">
				<?php esc_html_e( 'Select the type of structured data to use for this content.', 'crispy-seo' ); ?>
			</p>
		</div>

		<div class="crispy-seo-notice crispy-seo-notice--info">
			<p>
				<?php esc_html_e( 'Schema.org structured data helps search engines understand your content better and can enable rich results in search.', 'crispy-seo' ); ?>
			</p>
		</div>
	</div>

	<!-- Advanced Tab -->
	<div class="crispy-seo-panel" data-panel="advanced">
		<div class="crispy-seo-field">
			<label for="crispy_seo_canonical">
				<?php esc_html_e( 'Canonical URL', 'crispy-seo' ); ?>
			</label>
			<input type="url"
					id="crispy_seo_canonical"
					name="crispy_seo_canonical"
					value="<?php echo esc_url( $canonical ); ?>"
					class="large-text"
					placeholder="<?php echo esc_url( get_permalink( $post->ID ) ); ?>">
			<p class="description">
				<?php esc_html_e( 'Use this to specify the canonical URL if this content appears on multiple URLs.', 'crispy-seo' ); ?>
			</p>
		</div>

		<div class="crispy-seo-field">
			<label>
				<input type="checkbox"
						name="crispy_seo_noindex"
						value="1"
						<?php checked( $noindex, '1' ); ?>>
				<?php esc_html_e( 'Exclude from search results (noindex)', 'crispy-seo' ); ?>
			</label>
			<p class="description">
				<?php esc_html_e( 'Check this to prevent search engines from indexing this page.', 'crispy-seo' ); ?>
			</p>
		</div>

		<div class="crispy-seo-notice crispy-seo-notice--warning">
			<p>
				<strong><?php esc_html_e( 'Warning:', 'crispy-seo' ); ?></strong>
				<?php esc_html_e( 'Setting noindex will remove this page from search engine results.', 'crispy-seo' ); ?>
			</p>
		</div>
	</div>
</div>

<style>
.crispy-seo-metabox {
	margin: -6px -12px -12px;
}
.crispy-seo-tabs {
	display: flex;
	border-bottom: 1px solid #c3c4c7;
	background: #f6f7f7;
	padding: 0 12px;
}
.crispy-seo-tab {
	padding: 12px 16px;
	border: none;
	background: transparent;
	cursor: pointer;
	font-size: 13px;
	color: #50575e;
	border-bottom: 2px solid transparent;
	margin-bottom: -1px;
}
.crispy-seo-tab:hover {
	color: #1d2327;
}
.crispy-seo-tab--active {
	color: #2271b1;
	border-bottom-color: #2271b1;
	background: #fff;
}
.crispy-seo-panel {
	display: none;
	padding: 16px;
}
.crispy-seo-panel--active {
	display: block;
}
.crispy-seo-field {
	margin-bottom: 16px;
}
.crispy-seo-field label {
	display: block;
	font-weight: 600;
	margin-bottom: 8px;
}
.crispy-seo-counter {
	font-size: 12px;
	color: #50575e;
	margin-top: 4px;
	text-align: right;
}
.crispy-seo-preview {
	background: #f0f0f1;
	padding: 16px;
	border-radius: 4px;
	margin-top: 16px;
}
.crispy-seo-preview h4 {
	margin: 0 0 12px;
	font-size: 12px;
	text-transform: uppercase;
	color: #50575e;
}
.crispy-seo-preview__box {
	background: #fff;
	padding: 16px;
	border-radius: 4px;
	border: 1px solid #c3c4c7;
}
.crispy-seo-preview__title {
	color: #1a0dab;
	font-size: 18px;
	margin-bottom: 4px;
}
.crispy-seo-preview__url {
	color: #006621;
	font-size: 14px;
	margin-bottom: 4px;
}
.crispy-seo-preview__description {
	color: #545454;
	font-size: 13px;
	line-height: 1.4;
}
.crispy-seo-notice {
	padding: 12px;
	border-radius: 4px;
	margin-top: 16px;
}
.crispy-seo-notice--info {
	background: #d7e9f7;
	border-left: 4px solid #2271b1;
}
.crispy-seo-notice--warning {
	background: #fcf0e3;
	border-left: 4px solid #dba617;
}
.crispy-seo-notice p {
	margin: 0;
}
</style>
