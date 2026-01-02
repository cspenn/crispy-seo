<?php
/**
 * XML Sitemap Generator
 *
 * @package CrispySEO\Technical
 */

declare(strict_types=1);

namespace CrispySEO\Technical;

/**
 * Generates XML sitemaps.
 */
class Sitemap {

	/**
	 * Register rewrite rules for sitemap.
	 */
	public function registerRewriteRules(): void {
		if ( ! get_option( 'crispy_seo_sitemap_enabled', true ) ) {
			return;
		}

		add_action( 'init', [ $this, 'addRewriteRules' ] );
		add_filter( 'query_vars', [ $this, 'addQueryVars' ] );
		add_action( 'template_redirect', [ $this, 'handleSitemapRequest' ] );

		// Add sitemap to robots.txt.
		add_filter( 'robots_txt', [ $this, 'addSitemapToRobots' ], 10, 2 );
	}

	/**
	 * Add rewrite rules.
	 */
	public function addRewriteRules(): void {
		add_rewrite_rule(
			'^sitemap\.xml$',
			'index.php?crispy_sitemap=index',
			'top'
		);

		add_rewrite_rule(
			'^sitemap-([a-z0-9_-]+)\.xml$',
			'index.php?crispy_sitemap=$matches[1]',
			'top'
		);

		add_rewrite_rule(
			'^sitemap-([a-z0-9_-]+)-([0-9]+)\.xml$',
			'index.php?crispy_sitemap=$matches[1]&crispy_sitemap_page=$matches[2]',
			'top'
		);
	}

	/**
	 * Add query vars.
	 *
	 * @param array<string> $vars Existing query vars.
	 * @return array<string>
	 */
	public function addQueryVars( array $vars ): array {
		$vars[] = 'crispy_sitemap';
		$vars[] = 'crispy_sitemap_page';
		return $vars;
	}

	/**
	 * Handle sitemap request.
	 */
	public function handleSitemapRequest(): void {
		$sitemap = get_query_var( 'crispy_sitemap' );

		if ( empty( $sitemap ) ) {
			return;
		}

		$page = (int) get_query_var( 'crispy_sitemap_page', 1 );

		if ( $sitemap === 'index' ) {
			$this->renderIndex();
		} else {
			$this->renderSitemap( $sitemap, $page );
		}

		exit;
	}

	/**
	 * Render sitemap index.
	 */
	private function renderIndex(): void {
		header( 'Content-Type: application/xml; charset=utf-8' );
		header( 'X-Robots-Tag: noindex, follow' );

		echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
		echo '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

		// Post type sitemaps.
		$postTypes = $this->getEnabledPostTypes();
		foreach ( $postTypes as $postType ) {
			$count = $this->getPostTypeCount( $postType );
			$pages = (int) ceil( $count / 1000 );

			for ( $i = 1; $i <= $pages; $i++ ) {
				$url     = home_url( "/sitemap-{$postType}-{$i}.xml" );
				$lastmod = $this->getPostTypeLastMod( $postType );

				echo "  <sitemap>\n";
				echo '    <loc>' . esc_url( $url ) . "</loc>\n";
				if ( $lastmod ) {
					echo '    <lastmod>' . esc_html( $lastmod ) . "</lastmod>\n";
				}
				echo "  </sitemap>\n";
			}
		}

		// Taxonomy sitemaps.
		$taxonomies = $this->getEnabledTaxonomies();
		foreach ( $taxonomies as $taxonomy ) {
			$count = $this->getTaxonomyCount( $taxonomy );
			if ( $count > 0 ) {
				$url = home_url( "/sitemap-{$taxonomy}-1.xml" );
				echo "  <sitemap>\n";
				echo '    <loc>' . esc_url( $url ) . "</loc>\n";
				echo "  </sitemap>\n";
			}
		}

		echo '</sitemapindex>';
	}

	/**
	 * Render individual sitemap.
	 *
	 * @param string $type Sitemap type (post type or taxonomy).
	 * @param int    $page Page number.
	 */
	private function renderSitemap( string $type, int $page ): void {
		header( 'Content-Type: application/xml; charset=utf-8' );
		header( 'X-Robots-Tag: noindex, follow' );

		echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
		echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

		$postTypes  = $this->getEnabledPostTypes();
		$taxonomies = $this->getEnabledTaxonomies();

		if ( in_array( $type, $postTypes, true ) ) {
			$this->renderPostTypeSitemap( $type, $page );
		} elseif ( in_array( $type, $taxonomies, true ) ) {
			$this->renderTaxonomySitemap( $type, $page );
		}

		echo '</urlset>';
	}

	/**
	 * Render post type sitemap entries.
	 *
	 * @param string $postType Post type.
	 * @param int    $page     Page number.
	 */
	private function renderPostTypeSitemap( string $postType, int $page ): void {
		$offset = ( $page - 1 ) * 1000;

		$posts = get_posts(
			[
				'post_type'              => $postType,
				'post_status'            => 'publish',
				'posts_per_page'         => 1000,
				'offset'                 => $offset,
				'orderby'                => 'modified',
				'order'                  => 'DESC',
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			]
		);

		foreach ( $posts as $post ) {
			// Skip noindexed posts.
			if ( get_post_meta( $post->ID, '_crispy_seo_noindex', true ) === '1' ) {
				continue;
			}

			$url     = get_permalink( $post->ID );
			$lastmod = get_the_modified_date( 'c', $post );

			echo "  <url>\n";
			echo '    <loc>' . esc_url( $url ) . "</loc>\n";
			echo '    <lastmod>' . esc_html( $lastmod ) . "</lastmod>\n";
			echo "    <changefreq>weekly</changefreq>\n";
			echo '    <priority>' . $this->getPriority( $post ) . "</priority>\n";
			echo "  </url>\n";
		}
	}

	/**
	 * Render taxonomy sitemap entries.
	 *
	 * @param string $taxonomy Taxonomy name.
	 * @param int    $page     Page number.
	 */
	private function renderTaxonomySitemap( string $taxonomy, int $page ): void {
		$offset = ( $page - 1 ) * 1000;

		$terms = get_terms(
			[
				'taxonomy'   => $taxonomy,
				'hide_empty' => true,
				'number'     => 1000,
				'offset'     => $offset,
			]
		);

		if ( is_wp_error( $terms ) ) {
			return;
		}

		foreach ( $terms as $term ) {
			$url = get_term_link( $term );
			if ( is_wp_error( $url ) ) {
				continue;
			}

			echo "  <url>\n";
			echo '    <loc>' . esc_url( $url ) . "</loc>\n";
			echo "    <changefreq>weekly</changefreq>\n";
			echo "    <priority>0.6</priority>\n";
			echo "  </url>\n";
		}
	}

	/**
	 * Get enabled post types.
	 *
	 * @return array<string>
	 */
	private function getEnabledPostTypes(): array {
		$enabled = get_option( 'crispy_seo_sitemap_post_types', [] );

		if ( empty( $enabled ) ) {
			// Default to all public post types.
			$postTypes = get_post_types( [ 'public' => true ], 'names' );
			unset( $postTypes['attachment'] );
			return array_values( $postTypes );
		}

		return $enabled;
	}

	/**
	 * Get enabled taxonomies.
	 *
	 * @return array<string>
	 */
	private function getEnabledTaxonomies(): array {
		$enabled = get_option( 'crispy_seo_sitemap_taxonomies', [] );

		if ( empty( $enabled ) ) {
			// Default to category and post_tag.
			return [ 'category', 'post_tag' ];
		}

		return $enabled;
	}

	/**
	 * Get post count for a post type.
	 *
	 * @param string $postType Post type.
	 */
	private function getPostTypeCount( string $postType ): int {
		$counts = wp_count_posts( $postType );
		return (int) ( $counts->publish ?? 0 );
	}

	/**
	 * Get term count for a taxonomy.
	 *
	 * @param string $taxonomy Taxonomy name.
	 */
	private function getTaxonomyCount( string $taxonomy ): int {
		return (int) wp_count_terms(
			[
				'taxonomy'   => $taxonomy,
				'hide_empty' => true,
			]
		);
	}

	/**
	 * Get last modified date for a post type.
	 *
	 * @param string $postType Post type.
	 */
	private function getPostTypeLastMod( string $postType ): ?string {
		$posts = get_posts(
			[
				'post_type'      => $postType,
				'post_status'    => 'publish',
				'posts_per_page' => 1,
				'orderby'        => 'modified',
				'order'          => 'DESC',
				'no_found_rows'  => true,
			]
		);

		if ( ! empty( $posts ) ) {
			return get_the_modified_date( 'c', $posts[0] );
		}

		return null;
	}

	/**
	 * Get priority for a post.
	 *
	 * @param \WP_Post $post Post object.
	 */
	private function getPriority( \WP_Post $post ): string {
		// Front page gets highest priority.
		$frontPageId = (int) get_option( 'page_on_front' );
		if ( $post->ID === $frontPageId ) {
			return '1.0';
		}

		// Pages get slightly higher priority.
		if ( $post->post_type === 'page' ) {
			return '0.8';
		}

		// Recent posts get higher priority.
		$modified = strtotime( $post->post_modified );
		$age      = time() - $modified;
		$daysOld  = $age / DAY_IN_SECONDS;

		if ( $daysOld < 7 ) {
			return '0.8';
		} elseif ( $daysOld < 30 ) {
			return '0.7';
		} elseif ( $daysOld < 90 ) {
			return '0.6';
		}

		return '0.5';
	}

	/**
	 * Add sitemap URL to robots.txt.
	 *
	 * @param string $output Robots.txt content.
	 * @param bool   $public Site visibility.
	 */
	public function addSitemapToRobots( string $output, bool $public ): string {
		if ( ! $public ) {
			return $output;
		}

		$sitemapUrl = home_url( '/sitemap.xml' );

		if ( strpos( $output, 'Sitemap:' ) === false ) {
			$output .= "\nSitemap: " . $sitemapUrl . "\n";
		}

		return $output;
	}

	/**
	 * Ping search engines about sitemap update.
	 */
	public function pingSearchEngines(): void {
		$sitemapUrl = urlencode( home_url( '/sitemap.xml' ) );

		// Ping Google.
		wp_remote_get(
			"https://www.google.com/ping?sitemap={$sitemapUrl}",
			[
				'blocking' => false,
			]
		);

		// Ping Bing.
		wp_remote_get(
			"https://www.bing.com/ping?sitemap={$sitemapUrl}",
			[
				'blocking' => false,
			]
		);
	}
}
