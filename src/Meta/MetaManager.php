<?php
/**
 * Meta Tag Manager
 *
 * @package CrispySEO\Meta
 */

declare(strict_types=1);

namespace CrispySEO\Meta;

/**
 * Manages meta tags output.
 */
class MetaManager {

	/**
	 * Output meta tags.
	 */
	public function output(): void {
		$this->outputTitle();
		$this->outputDescription();
		$this->outputCanonical();
		$this->outputRobots();
		$this->outputOpenGraph();
		$this->outputTwitterCard();
	}

	/**
	 * Get the document title.
	 */
	public function getTitle(): string {
		if ( is_singular() ) {
			$post        = get_post();
			$customTitle = get_post_meta( $post->ID, '_crispy_seo_title', true );
			if ( ! empty( $customTitle ) ) {
				return $this->processTitle( $customTitle );
			}
		}

		return $this->getDefaultTitle();
	}

	/**
	 * Get default title based on context.
	 */
	private function getDefaultTitle(): string {
		$separator = get_option( 'crispy_seo_title_separator', '|' );
		$siteName  = get_bloginfo( 'name' );

		if ( is_front_page() ) {
			$customTitle = get_option( 'crispy_seo_homepage_title', '' );
			if ( ! empty( $customTitle ) ) {
				return $this->processTitle( $customTitle );
			}
			return $siteName;
		}

		if ( is_singular() ) {
			$post     = get_post();
			$template = get_option( 'crispy_seo_title_template_' . $post->post_type, '%%title%% %%sep%% %%sitename%%' );
			return $this->processTitle( $template, $post );
		}

		if ( is_category() || is_tag() || is_tax() ) {
			$term = get_queried_object();
			return sprintf( '%s %s %s', $term->name, $separator, $siteName );
		}

		if ( is_author() ) {
			$author = get_queried_object();
			return sprintf( '%s %s %s', $author->display_name, $separator, $siteName );
		}

		if ( is_search() ) {
			return sprintf(
				/* translators: %1$s: search query, %2$s: separator, %3$s: site name */
				__( 'Search Results for "%1$s" %2$s %3$s', 'crispy-seo' ),
				get_search_query(),
				$separator,
				$siteName
			);
		}

		if ( is_404() ) {
			return sprintf(
				/* translators: %1$s: separator, %2$s: site name */
				__( 'Page Not Found %1$s %2$s', 'crispy-seo' ),
				$separator,
				$siteName
			);
		}

		if ( is_archive() ) {
			return sprintf( '%s %s %s', get_the_archive_title(), $separator, $siteName );
		}

		return $siteName;
	}

	/**
	 * Process title template with variables.
	 *
	 * @param string        $template Title template.
	 * @param \WP_Post|null $post     Post object.
	 */
	private function processTitle( string $template, ?\WP_Post $post = null ): string {
		$replacements = [
			'%%title%%'    => $post ? $post->post_title : '',
			'%%sitename%%' => get_bloginfo( 'name' ),
			'%%sep%%'      => get_option( 'crispy_seo_title_separator', '|' ),
			'%%sitedesc%%' => get_bloginfo( 'description' ),
			'%%date%%'     => $post ? get_the_date( '', $post ) : '',
			'%%author%%'   => $post ? get_the_author_meta( 'display_name', $post->post_author ) : '',
		];

		return str_replace( array_keys( $replacements ), array_values( $replacements ), $template );
	}

	/**
	 * Output title tag (WordPress handles this via document_title filter).
	 */
	private function outputTitle(): void {
		add_filter( 'pre_get_document_title', [ $this, 'getTitle' ], 15 );
	}

	/**
	 * Get meta description.
	 */
	public function getDescription(): string {
		if ( is_singular() ) {
			$post       = get_post();
			$customDesc = get_post_meta( $post->ID, '_crispy_seo_description', true );
			if ( ! empty( $customDesc ) ) {
				return $customDesc;
			}

			// Auto-generate from content.
			$content = $post->post_content;
			if ( has_post_meta( $post->ID, '_markdown_content' ) ) {
				$content = get_post_meta( $post->ID, '_markdown_content', true );
			}
			return $this->generateExcerpt( $content, 160 );
		}

		if ( is_front_page() ) {
			$customDesc = get_option( 'crispy_seo_homepage_description', '' );
			if ( ! empty( $customDesc ) ) {
				return $customDesc;
			}
			return get_bloginfo( 'description' );
		}

		if ( is_category() || is_tag() || is_tax() ) {
			$term = get_queried_object();
			if ( ! empty( $term->description ) ) {
				return $this->generateExcerpt( $term->description, 160 );
			}
		}

		if ( is_author() ) {
			$author = get_queried_object();
			$bio    = get_the_author_meta( 'description', $author->ID );
			if ( ! empty( $bio ) ) {
				return $this->generateExcerpt( $bio, 160 );
			}
		}

		return get_bloginfo( 'description' );
	}

	/**
	 * Generate excerpt from content.
	 *
	 * @param string $content Content to excerpt.
	 * @param int    $length  Maximum length.
	 */
	private function generateExcerpt( string $content, int $length = 160 ): string {
		// Strip markdown/HTML.
		$text = wp_strip_all_tags( $content );
		$text = preg_replace( '/\s+/', ' ', $text );
		$text = trim( $text );

		if ( strlen( $text ) <= $length ) {
			return $text;
		}

		$excerpt   = substr( $text, 0, $length );
		$lastSpace = strrpos( $excerpt, ' ' );
		if ( $lastSpace !== false ) {
			$excerpt = substr( $excerpt, 0, $lastSpace );
		}

		return $excerpt . '...';
	}

	/**
	 * Output meta description.
	 */
	private function outputDescription(): void {
		$description = $this->getDescription();
		if ( ! empty( $description ) ) {
			printf(
				'<meta name="description" content="%s" />' . "\n",
				esc_attr( $description )
			);
		}
	}

	/**
	 * Output canonical URL.
	 */
	private function outputCanonical(): void {
		$canonical = '';

		if ( is_singular() ) {
			$post            = get_post();
			$customCanonical = get_post_meta( $post->ID, '_crispy_seo_canonical', true );
			$canonical       = ! empty( $customCanonical ) ? $customCanonical : get_permalink( $post );
		} elseif ( is_front_page() ) {
			$canonical = home_url( '/' );
		} elseif ( is_category() || is_tag() || is_tax() ) {
			$canonical = get_term_link( get_queried_object() );
		} elseif ( is_author() ) {
			$canonical = get_author_posts_url( get_queried_object_id() );
		} elseif ( is_archive() ) {
			if ( is_date() ) {
				if ( is_day() ) {
					$canonical = get_day_link( get_query_var( 'year' ), get_query_var( 'monthnum' ), get_query_var( 'day' ) );
				} elseif ( is_month() ) {
					$canonical = get_month_link( get_query_var( 'year' ), get_query_var( 'monthnum' ) );
				} else {
					$canonical = get_year_link( get_query_var( 'year' ) );
				}
			}
		}

		// Handle pagination.
		$paged = get_query_var( 'paged', 0 );
		if ( $paged > 1 && ! empty( $canonical ) ) {
			$canonical = trailingslashit( $canonical ) . 'page/' . $paged . '/';
		}

		if ( ! empty( $canonical ) ) {
			printf( '<link rel="canonical" href="%s" />' . "\n", esc_url( $canonical ) );
		}
	}

	/**
	 * Output robots meta.
	 */
	private function outputRobots(): void {
		$robots = [];

		if ( is_singular() ) {
			$post    = get_post();
			$noindex = get_post_meta( $post->ID, '_crispy_seo_noindex', true );
			if ( $noindex === '1' ) {
				$robots[] = 'noindex';
			}
		}

		// Don't index search results or 404.
		if ( is_search() || is_404() ) {
			$robots[] = 'noindex';
		}

		if ( ! empty( $robots ) ) {
			printf(
				'<meta name="robots" content="%s" />' . "\n",
				esc_attr( implode( ', ', $robots ) )
			);
		}
	}

	/**
	 * Output Open Graph tags.
	 */
	private function outputOpenGraph(): void {
		$og = [
			'og:locale'      => get_locale(),
			'og:type'        => is_singular() ? 'article' : 'website',
			'og:title'       => $this->getOgTitle(),
			'og:description' => $this->getOgDescription(),
			'og:url'         => $this->getOgUrl(),
			'og:site_name'   => get_bloginfo( 'name' ),
		];

		// Add image.
		$image = $this->getOgImage();
		if ( ! empty( $image ) ) {
			$og['og:image'] = $image;
		}

		// Add article-specific tags.
		if ( is_singular() ) {
			$post                         = get_post();
			$og['article:published_time'] = get_the_date( 'c', $post );
			$og['article:modified_time']  = get_the_modified_date( 'c', $post );
			$og['article:author']         = get_the_author_meta( 'display_name', $post->post_author );
		}

		// Add Facebook App ID.
		$fbAppId = get_option( 'crispy_seo_facebook_app_id', '' );
		if ( ! empty( $fbAppId ) ) {
			$og['fb:app_id'] = $fbAppId;
		}

		// Output tags.
		foreach ( $og as $property => $content ) {
			if ( ! empty( $content ) ) {
				printf(
					'<meta property="%s" content="%s" />' . "\n",
					esc_attr( $property ),
					esc_attr( $content )
				);
			}
		}
	}

	/**
	 * Get Open Graph title.
	 */
	private function getOgTitle(): string {
		if ( is_singular() ) {
			$post        = get_post();
			$customTitle = get_post_meta( $post->ID, '_crispy_seo_og_title', true );
			if ( ! empty( $customTitle ) ) {
				return $customTitle;
			}
		}
		return $this->getTitle();
	}

	/**
	 * Get Open Graph description.
	 */
	private function getOgDescription(): string {
		if ( is_singular() ) {
			$post       = get_post();
			$customDesc = get_post_meta( $post->ID, '_crispy_seo_og_description', true );
			if ( ! empty( $customDesc ) ) {
				return $customDesc;
			}
		}
		return $this->getDescription();
	}

	/**
	 * Get Open Graph URL.
	 */
	private function getOgUrl(): string {
		if ( is_singular() ) {
			return get_permalink();
		}
		if ( is_front_page() ) {
			return home_url( '/' );
		}
		global $wp;
		return home_url( $wp->request );
	}

	/**
	 * Get Open Graph image.
	 */
	private function getOgImage(): string {
		if ( is_singular() ) {
			$post = get_post();

			// Custom OG image.
			$customImage = get_post_meta( $post->ID, '_crispy_seo_og_image', true );
			if ( ! empty( $customImage ) ) {
				return $customImage;
			}

			// Featured image.
			if ( has_post_thumbnail( $post->ID ) ) {
				$imageId  = get_post_thumbnail_id( $post->ID );
				$imageSrc = wp_get_attachment_image_src( $imageId, 'large' );
				if ( $imageSrc ) {
					return $imageSrc[0];
				}
			}
		}

		// Default OG image.
		return get_option( 'crispy_seo_og_default_image', '' );
	}

	/**
	 * Output Twitter Card tags.
	 */
	private function outputTwitterCard(): void {
		$twitter = [
			'twitter:card'        => get_option( 'crispy_seo_twitter_card_type', 'summary_large_image' ),
			'twitter:title'       => $this->getTwitterTitle(),
			'twitter:description' => $this->getTwitterDescription(),
		];

		// Add image.
		$image = $this->getTwitterImage();
		if ( ! empty( $image ) ) {
			$twitter['twitter:image'] = $image;
		}

		// Add site handle.
		$siteHandle = get_option( 'crispy_seo_twitter_site', '' );
		if ( ! empty( $siteHandle ) ) {
			$twitter['twitter:site'] = $siteHandle;
		}

		// Output tags.
		foreach ( $twitter as $name => $content ) {
			if ( ! empty( $content ) ) {
				printf(
					'<meta name="%s" content="%s" />' . "\n",
					esc_attr( $name ),
					esc_attr( $content )
				);
			}
		}
	}

	/**
	 * Get Twitter title.
	 */
	private function getTwitterTitle(): string {
		if ( is_singular() ) {
			$post        = get_post();
			$customTitle = get_post_meta( $post->ID, '_crispy_seo_twitter_title', true );
			if ( ! empty( $customTitle ) ) {
				return $customTitle;
			}
		}
		return $this->getOgTitle();
	}

	/**
	 * Get Twitter description.
	 */
	private function getTwitterDescription(): string {
		if ( is_singular() ) {
			$post       = get_post();
			$customDesc = get_post_meta( $post->ID, '_crispy_seo_twitter_description', true );
			if ( ! empty( $customDesc ) ) {
				return $customDesc;
			}
		}
		return $this->getOgDescription();
	}

	/**
	 * Get Twitter image.
	 */
	private function getTwitterImage(): string {
		if ( is_singular() ) {
			$post        = get_post();
			$customImage = get_post_meta( $post->ID, '_crispy_seo_twitter_image', true );
			if ( ! empty( $customImage ) ) {
				return $customImage;
			}
		}
		return $this->getOgImage();
	}
}
