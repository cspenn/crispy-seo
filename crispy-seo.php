<?php
/**
 * CrispySEO
 *
 * A comprehensive SEO plugin bundled with CrispyTheme.
 * Replaces the need for Yoast SEO, RankMath, or All in One SEO.
 *
 * @package     CrispySEO
 * @author      Christopher S. Penn
 * @copyright   2024 Christopher S. Penn
 * @license     MIT
 *
 * @wordpress-plugin
 * Plugin Name: CrispySEO
 * Plugin URI:  https://christopherspenn.com/crispytheme
 * Description: Comprehensive SEO plugin bundled with CrispyTheme. Meta tags, JSON-LD, sitemaps, redirects, analytics, and more.
 * Version:     1.0.5
 * Author:      Christopher S. Penn
 * Author URI:  https://christopherspenn.com
 * Text Domain: crispy-seo
 * Domain Path: /languages
 * License:     MIT
 * Requires at least: 6.6
 * Requires PHP: 8.1
 */

declare(strict_types=1);

namespace CrispySEO;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Plugin constants.
define( 'CRISPY_SEO_VERSION', '1.0.5' );
define( 'CRISPY_SEO_FILE', __FILE__ );
define( 'CRISPY_SEO_DIR', plugin_dir_path( __FILE__ ) );
define( 'CRISPY_SEO_URL', plugin_dir_url( __FILE__ ) );
define( 'CRISPY_SEO_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Main plugin class.
 */
final class CrispySEO {

	/**
	 * Singleton instance.
	 */
	private static ?self $instance = null;

	/**
	 * Plugin components.
	 *
	 * @var array<string, object>
	 */
	private array $components = [];

	/**
	 * Get singleton instance.
	 */
	public static function getInstance(): self {
		if ( self::$instance === null ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Private constructor.
	 */
	private function __construct() {
		$this->loadDependencies();
		$this->initHooks();
	}

	/**
	 * Load autoloader and dependencies.
	 */
	private function loadDependencies(): void {
		// Load Composer autoloader.
		$autoloader = CRISPY_SEO_DIR . 'vendor/autoload.php';

		if ( file_exists( $autoloader ) ) {
			require_once $autoloader;
		}
	}

	/**
	 * Initialize WordPress hooks.
	 */
	private function initHooks(): void {
		// Initialize components after WordPress is loaded.
		add_action( 'init', [ $this, 'init' ], 5 );

		// Admin hooks.
		if ( is_admin() ) {
			add_action( 'admin_menu', [ $this, 'registerAdminMenu' ] );
			add_action( 'admin_init', [ $this, 'registerSettings' ] );
			add_action( 'add_meta_boxes', [ $this, 'registerMetaBoxes' ] );
			add_action( 'save_post', [ $this, 'saveMetaBoxData' ], 10, 2 );
			add_action( 'admin_enqueue_scripts', [ $this, 'enqueueAdminAssets' ] );
		}

		// Frontend hooks.
		add_action( 'wp_head', [ $this, 'outputMetaTags' ], 1 );
		add_action( 'wp_head', [ $this, 'outputJsonLd' ], 2 );
		add_action( 'wp_footer', [ $this, 'outputAnalytics' ], 100 );

		// Sitemap hooks.
		add_action( 'init', [ $this, 'initSitemap' ] );

		// Robots.txt filter.
		add_filter( 'robots_txt', [ $this, 'filterRobotsTxt' ], 10, 2 );

		// Migration notice.
		add_action( 'admin_notices', [ $this, 'showMigrationNotice' ] );
	}

	/**
	 * Initialize plugin components.
	 */
	public function init(): void {
		// Load text domain.
		load_plugin_textdomain(
			'crispy-seo',
			false,
			dirname( CRISPY_SEO_BASENAME ) . '/languages'
		);

		// Initialize core components.
		$this->components['meta']      = new Meta\MetaManager();
		$this->components['schema']    = new Schema\SchemaFactory();
		$this->components['sitemap']   = new Technical\Sitemap();
		$this->components['redirect_manager'] = new Technical\RedirectManager();
		$this->components['analytics'] = new Analytics\AnalyticsManager();
		$this->components['social']          = new Social\ShareButtons();
		$this->components['search_replace']  = new Tools\SearchReplace();
		$this->components['internal_links']  = new Content\InternalLinkManager();
		$this->components['image_optimizer'] = new Media\ImageOptimizer();
		$this->components['media_replacer']  = new Media\MediaReplacer();
		$this->components['not_found']       = new Technical\NotFoundManager();

		// Allow extensions.
		do_action( 'crispy_seo_init', $this );
	}

	/**
	 * Register admin menu pages.
	 */
	public function registerAdminMenu(): void {
		add_menu_page(
			__( 'CrispySEO', 'crispy-seo' ),
			__( 'CrispySEO', 'crispy-seo' ),
			'manage_options',
			'crispy-seo',
			[ $this, 'renderSettingsPage' ],
			'dashicons-search',
			80
		);

		add_submenu_page(
			'crispy-seo',
			__( 'General Settings', 'crispy-seo' ),
			__( 'General', 'crispy-seo' ),
			'manage_options',
			'crispy-seo',
			[ $this, 'renderSettingsPage' ]
		);

		add_submenu_page(
			'crispy-seo',
			__( 'Titles & Meta', 'crispy-seo' ),
			__( 'Titles & Meta', 'crispy-seo' ),
			'manage_options',
			'crispy-seo-titles',
			[ $this, 'renderTitlesPage' ]
		);

		add_submenu_page(
			'crispy-seo',
			__( 'Social', 'crispy-seo' ),
			__( 'Social', 'crispy-seo' ),
			'manage_options',
			'crispy-seo-social',
			[ $this, 'renderSocialPage' ]
		);

		add_submenu_page(
			'crispy-seo',
			__( 'Schema', 'crispy-seo' ),
			__( 'Schema', 'crispy-seo' ),
			'manage_options',
			'crispy-seo-schema',
			[ $this, 'renderSchemaPage' ]
		);

		add_submenu_page(
			'crispy-seo',
			__( 'Sitemap', 'crispy-seo' ),
			__( 'Sitemap', 'crispy-seo' ),
			'manage_options',
			'crispy-seo-sitemap',
			[ $this, 'renderSitemapPage' ]
		);

		add_submenu_page(
			'crispy-seo',
			__( 'Redirects', 'crispy-seo' ),
			__( 'Redirects', 'crispy-seo' ),
			'manage_options',
			'crispy-seo-redirects',
			[ $this, 'renderRedirectsPage' ]
		);

		add_submenu_page(
			'crispy-seo',
			__( 'Analytics', 'crispy-seo' ),
			__( 'Analytics', 'crispy-seo' ),
			'manage_options',
			'crispy-seo-analytics',
			[ $this, 'renderAnalyticsPage' ]
		);

		add_submenu_page(
			'crispy-seo',
			__( '404 Monitor', 'crispy-seo' ),
			__( '404 Monitor', 'crispy-seo' ),
			'manage_options',
			'crispy-seo-404',
			[ $this, 'render404Page' ]
		);

		add_submenu_page(
			'crispy-seo',
			__( 'Tools', 'crispy-seo' ),
			__( 'Tools', 'crispy-seo' ),
			'manage_options',
			'crispy-seo-tools',
			[ $this, 'renderToolsPage' ]
		);
	}

	/**
	 * Register plugin settings.
	 */
	public function registerSettings(): void {
		// General settings.
		register_setting( 'crispy_seo_general', 'crispy_seo_title_separator' );
		register_setting( 'crispy_seo_general', 'crispy_seo_homepage_title' );
		register_setting( 'crispy_seo_general', 'crispy_seo_homepage_description' );

		// Title templates.
		register_setting( 'crispy_seo_titles', 'crispy_seo_title_template_post' );
		register_setting( 'crispy_seo_titles', 'crispy_seo_title_template_page' );
		register_setting( 'crispy_seo_titles', 'crispy_seo_title_template_archive' );
		register_setting( 'crispy_seo_titles', 'crispy_seo_title_template_author' );
		register_setting( 'crispy_seo_titles', 'crispy_seo_title_template_search' );
		register_setting( 'crispy_seo_titles', 'crispy_seo_title_template_404' );

		// Social settings.
		register_setting( 'crispy_seo_social', 'crispy_seo_og_default_image' );
		register_setting( 'crispy_seo_social', 'crispy_seo_twitter_card_type' );
		register_setting( 'crispy_seo_social', 'crispy_seo_twitter_site' );
		register_setting( 'crispy_seo_social', 'crispy_seo_facebook_app_id' );

		// Schema settings.
		register_setting( 'crispy_seo_schema', 'crispy_seo_organization_name' );
		register_setting( 'crispy_seo_schema', 'crispy_seo_organization_logo' );
		register_setting( 'crispy_seo_schema', 'crispy_seo_default_schema_type' );

		// Sitemap settings.
		register_setting( 'crispy_seo_sitemap', 'crispy_seo_sitemap_enabled' );
		register_setting( 'crispy_seo_sitemap', 'crispy_seo_sitemap_post_types' );
		register_setting( 'crispy_seo_sitemap', 'crispy_seo_sitemap_taxonomies' );

		// Analytics settings.
		register_setting( 'crispy_seo_analytics', 'crispy_seo_ga4_id' );
		register_setting( 'crispy_seo_analytics', 'crispy_seo_plausible_domain' );
		register_setting( 'crispy_seo_analytics', 'crispy_seo_fathom_site_id' );
		register_setting( 'crispy_seo_analytics', 'crispy_seo_matomo_url' );
		register_setting( 'crispy_seo_analytics', 'crispy_seo_matomo_site_id' );

		// Robots.txt settings.
		register_setting( 'crispy_seo_tools', 'crispy_seo_robots_txt' );
		register_setting( 'crispy_seo_tools', 'crispy_seo_llms_txt' );
	}

	/**
	 * Register post meta boxes.
	 */
	public function registerMetaBoxes(): void {
		$postTypes = get_post_types( [ 'public' => true ], 'names' );

		foreach ( $postTypes as $postType ) {
			add_meta_box(
				'crispy-seo-meta',
				__( 'CrispySEO', 'crispy-seo' ),
				[ $this, 'renderMetaBox' ],
				$postType,
				'normal',
				'high'
			);
		}
	}

	/**
	 * Render the SEO meta box.
	 *
	 * @param \WP_Post $post The post object.
	 */
	public function renderMetaBox( \WP_Post $post ): void {
		wp_nonce_field( 'crispy_seo_meta', 'crispy_seo_meta_nonce' );

		$metaTitle       = get_post_meta( $post->ID, '_crispy_seo_title', true );
		$metaDescription = get_post_meta( $post->ID, '_crispy_seo_description', true );
		$focusKeyword    = get_post_meta( $post->ID, '_crispy_seo_focus_keyword', true );
		$schemaType      = get_post_meta( $post->ID, '_crispy_seo_schema_type', true );
		$canonical       = get_post_meta( $post->ID, '_crispy_seo_canonical', true );
		$noindex         = get_post_meta( $post->ID, '_crispy_seo_noindex', true );

		include CRISPY_SEO_DIR . 'views/meta-box.php';
	}

	/**
	 * Save meta box data.
	 *
	 * @param int      $postId Post ID.
	 * @param \WP_Post $post   Post object.
	 */
	public function saveMetaBoxData( int $postId, \WP_Post $post ): void {
		// Verify nonce.
		if ( ! isset( $_POST['crispy_seo_meta_nonce'] ) ||
			! wp_verify_nonce( $_POST['crispy_seo_meta_nonce'], 'crispy_seo_meta' ) ) {
			return;
		}

		// Check autosave.
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		// Check permissions.
		if ( ! current_user_can( 'edit_post', $postId ) ) {
			return;
		}

		// Save meta fields.
		$fields = [
			'_crispy_seo_title',
			'_crispy_seo_description',
			'_crispy_seo_focus_keyword',
			'_crispy_seo_schema_type',
			'_crispy_seo_canonical',
			'_crispy_seo_noindex',
			'_crispy_seo_og_title',
			'_crispy_seo_og_description',
			'_crispy_seo_og_image',
			'_crispy_seo_twitter_title',
			'_crispy_seo_twitter_description',
			'_crispy_seo_twitter_image',
		];

		foreach ( $fields as $field ) {
			$key = str_replace( '_crispy_seo_', 'crispy_seo_', $field );
			if ( isset( $_POST[ $key ] ) ) {
				update_post_meta( $postId, $field, sanitize_text_field( $_POST[ $key ] ) );
			}
		}
	}

	/**
	 * Enqueue admin assets.
	 *
	 * @param string $hook Current admin page.
	 */
	public function enqueueAdminAssets( string $hook ): void {
		$screen = get_current_screen();

		// Only on our settings pages or post edit screens.
		if ( strpos( $hook, 'crispy-seo' ) !== false ||
			in_array( $screen->base ?? '', [ 'post', 'page' ], true ) ) {
			wp_enqueue_style(
				'crispy-seo-admin',
				CRISPY_SEO_URL . 'assets/css/admin.css',
				[],
				CRISPY_SEO_VERSION
			);

			wp_enqueue_script(
				'crispy-seo-admin',
				CRISPY_SEO_URL . 'assets/js/admin.js',
				[ 'jquery' ],
				CRISPY_SEO_VERSION,
				true
			);

			wp_localize_script(
				'crispy-seo-admin',
				'crispySeoAdmin',
				[
					'ajaxUrl' => admin_url( 'admin-ajax.php' ),
					'nonce'   => wp_create_nonce( 'crispy_seo_admin' ),
				]
			);
		}
	}

	/**
	 * Output meta tags in the head.
	 */
	public function outputMetaTags(): void {
		if ( isset( $this->components['meta'] ) ) {
			$this->components['meta']->output();
		}
	}

	/**
	 * Output JSON-LD structured data.
	 */
	public function outputJsonLd(): void {
		if ( isset( $this->components['schema'] ) ) {
			$this->components['schema']->output();
		}
	}

	/**
	 * Output analytics code in footer.
	 */
	public function outputAnalytics(): void {
		if ( isset( $this->components['analytics'] ) ) {
			$this->components['analytics']->output();
		}
	}

	/**
	 * Initialize sitemap rewrite rules.
	 */
	public function initSitemap(): void {
		if ( isset( $this->components['sitemap'] ) ) {
			$this->components['sitemap']->registerRewriteRules();
		}
	}

	/**
	 * Filter robots.txt content.
	 *
	 * @param string $output Robots.txt content.
	 * @param bool   $public Site visibility.
	 * @return string Modified content.
	 */
	public function filterRobotsTxt( string $output, bool $public ): string {
		$customRobots = get_option( 'crispy_seo_robots_txt', '' );
		if ( ! empty( $customRobots ) ) {
			return $customRobots;
		}
		return $output;
	}

	/**
	 * Show migration notice for detected SEO plugins.
	 */
	public function showMigrationNotice(): void {
		// Only show on our settings pages.
		$screen = get_current_screen();
		if ( ! $screen || strpos( $screen->id, 'crispy-seo' ) === false ) {
			return;
		}

		$detectedPlugins = [];

		if ( defined( 'WPSEO_VERSION' ) ) {
			$detectedPlugins[] = 'Yoast SEO';
		}
		if ( class_exists( 'RankMath' ) ) {
			$detectedPlugins[] = 'RankMath';
		}
		if ( class_exists( 'AIOSEO\Plugin\AIOSEO' ) ) {
			$detectedPlugins[] = 'All in One SEO';
		}

		if ( ! empty( $detectedPlugins ) && ! get_option( 'crispy_seo_migration_dismissed' ) ) {
			$plugins = implode( ', ', $detectedPlugins );
			?>
			<div class="notice notice-info is-dismissible">
				<p>
					<?php
					printf(
						/* translators: %s: list of detected plugins */
						esc_html__(
							'CrispySEO detected the following SEO plugins: %s. Would you like to import your settings?',
							'crispy-seo'
						),
						'<strong>' . esc_html( $plugins ) . '</strong>'
					);
					?>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=crispy-seo-tools&tab=import' ) ); ?>" class="button button-primary">
						<?php esc_html_e( 'Import Settings', 'crispy-seo' ); ?>
					</a>
				</p>
			</div>
			<?php
		}
	}

	/**
	 * Render settings page.
	 */
	public function renderSettingsPage(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		include CRISPY_SEO_DIR . 'views/settings-general.php';
	}

	/**
	 * Render titles page.
	 */
	public function renderTitlesPage(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		include CRISPY_SEO_DIR . 'views/settings-titles.php';
	}

	/**
	 * Render social page.
	 */
	public function renderSocialPage(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		include CRISPY_SEO_DIR . 'views/settings-social.php';
	}

	/**
	 * Render schema page.
	 */
	public function renderSchemaPage(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		include CRISPY_SEO_DIR . 'views/settings-schema.php';
	}

	/**
	 * Render sitemap page.
	 */
	public function renderSitemapPage(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		include CRISPY_SEO_DIR . 'views/settings-sitemap.php';
	}

	/**
	 * Render redirects page.
	 */
	public function renderRedirectsPage(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		include CRISPY_SEO_DIR . 'views/settings-redirects.php';
	}

	/**
	 * Render analytics page.
	 */
	public function renderAnalyticsPage(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		include CRISPY_SEO_DIR . 'views/settings-analytics.php';
	}

	/**
	 * Render tools page.
	 */
	public function renderToolsPage(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		include CRISPY_SEO_DIR . 'views/settings-tools.php';
	}

	/**
	 * Render 404 monitor page.
	 */
	public function render404Page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		include CRISPY_SEO_DIR . 'views/settings-404.php';
	}

	/**
	 * Get a plugin component.
	 *
	 * @param string $name Component name.
	 * @return object|null Component instance or null.
	 */
	public function getComponent( string $name ): ?object {
		return $this->components[ $name ] ?? null;
	}
}

/**
 * Initialize the plugin.
 */
function crispy_seo(): CrispySEO {
	return CrispySEO::getInstance();
}

// Start the plugin.
crispy_seo();

// Load global function wrappers (for non-namespaced access).
require_once CRISPY_SEO_DIR . 'includes/global-functions.php';

/**
 * Plugin activation hook.
 *
 * Creates database tables for enhanced features.
 */
register_activation_hook(
	CRISPY_SEO_FILE,
	function (): void {
		// Create redirect manager table.
		$redirectInstaller = new Technical\RedirectInstaller();
		$redirectInstaller->install();

		// Create internal link tables.
		$linkInstaller = new Content\LinkInstaller();
		$linkInstaller->install();

		// Create optimization queue table.
		$optimizationInstaller = new Media\OptimizationInstaller();
		$optimizationInstaller->install();

		// Create 404 logs table.
		$notFoundInstaller = new Technical\NotFoundInstaller();
		$notFoundInstaller->install();

		// Schedule 404 log cleanup.
		if ( ! wp_next_scheduled( 'crispy_seo_cleanup_404_logs' ) ) {
			wp_schedule_event( time(), 'daily', 'crispy_seo_cleanup_404_logs' );
		}

		// Flush rewrite rules for any new endpoints.
		flush_rewrite_rules();
	}
);

/**
 * Plugin deactivation hook.
 *
 * Cleans up scheduled events.
 */
register_deactivation_hook(
	CRISPY_SEO_FILE,
	function (): void {
		// Clear scheduled events.
		wp_clear_scheduled_hook( 'crispy_seo_build_link_index' );
		wp_clear_scheduled_hook( 'crispy_seo_process_image_queue' );
		wp_clear_scheduled_hook( 'crispy_seo_cleanup_404_logs' );

		// Flush rewrite rules.
		flush_rewrite_rules();
	}
);

/**
 * Register WP-CLI commands.
 */
if ( defined( 'WP_CLI' ) && WP_CLI ) {
	WP_CLI::add_command( 'crispy-seo redirect', CLI\RedirectCommand::class );
	WP_CLI::add_command( 'crispy-seo search-replace', CLI\SearchReplaceCommand::class );
	WP_CLI::add_command( 'crispy-seo links', CLI\LinkCommand::class );
	WP_CLI::add_command( 'crispy-seo optimize', CLI\OptimizeCommand::class );
}
