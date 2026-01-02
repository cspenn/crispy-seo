<?php
/**
 * Schema Factory
 *
 * @package CrispySEO\Schema
 */

declare(strict_types=1);

namespace CrispySEO\Schema;

/**
 * Generates JSON-LD structured data.
 */
class SchemaFactory {

	/**
	 * Available schema types.
	 */
	public const SCHEMA_TYPES = [
		'Article',
		'BlogPosting',
		'NewsArticle',
		'TechArticle',
		'Person',
		'Organization',
		'WebSite',
		'WebPage',
		'BreadcrumbList',
		'FAQPage',
		'HowTo',
		'Product',
		'Review',
		'Event',
		'LocalBusiness',
		'Recipe',
		'Course',
		'Book',
		'SoftwareApplication',
		'VideoObject',
	];

	/**
	 * Output JSON-LD structured data.
	 */
	public function output(): void {
		$schemas = $this->generate();

		foreach ( $schemas as $schema ) {
			printf(
				'<script type="application/ld+json">%s</script>' . "\n",
				wp_json_encode( $schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT )
			);
		}
	}

	/**
	 * Generate all applicable schemas.
	 *
	 * @return array<array<string, mixed>>
	 */
	public function generate(): array {
		$schemas = [];

		// Always add WebSite schema on front page.
		if ( is_front_page() ) {
			$schemas[] = $this->generateWebSite();
		}

		// Add Organization schema.
		$schemas[] = $this->generateOrganization();

		// Add Breadcrumbs.
		if ( ! is_front_page() ) {
			$breadcrumbs = $this->generateBreadcrumbs();
			if ( ! empty( $breadcrumbs ) ) {
				$schemas[] = $breadcrumbs;
			}
		}

		// Add page-specific schema.
		if ( is_singular() ) {
			$postSchema = $this->generatePostSchema();
			if ( ! empty( $postSchema ) ) {
				$schemas[] = $postSchema;
			}
		}

		return array_filter( $schemas );
	}

	/**
	 * Generate WebSite schema.
	 *
	 * @return array<string, mixed>
	 */
	private function generateWebSite(): array {
		$schema = [
			'@context'    => 'https://schema.org',
			'@type'       => 'WebSite',
			'name'        => get_bloginfo( 'name' ),
			'description' => get_bloginfo( 'description' ),
			'url'         => home_url( '/' ),
		];

		// Add search action.
		$schema['potentialAction'] = [
			'@type'       => 'SearchAction',
			'target'      => [
				'@type'       => 'EntryPoint',
				'urlTemplate' => home_url( '/?s={search_term_string}' ),
			],
			'query-input' => 'required name=search_term_string',
		];

		return $schema;
	}

	/**
	 * Generate Organization schema.
	 *
	 * @return array<string, mixed>
	 */
	private function generateOrganization(): array {
		$orgName = get_option( 'crispy_seo_organization_name', get_bloginfo( 'name' ) );
		$orgLogo = get_option( 'crispy_seo_organization_logo', '' );

		$schema = [
			'@context' => 'https://schema.org',
			'@type'    => 'Organization',
			'name'     => $orgName,
			'url'      => home_url( '/' ),
		];

		if ( ! empty( $orgLogo ) ) {
			$schema['logo'] = [
				'@type' => 'ImageObject',
				'url'   => $orgLogo,
			];
		}

		return $schema;
	}

	/**
	 * Generate BreadcrumbList schema.
	 *
	 * @return array<string, mixed>|null
	 */
	private function generateBreadcrumbs(): ?array {
		$items    = [];
		$position = 1;

		// Home.
		$items[] = [
			'@type'    => 'ListItem',
			'position' => $position++,
			'name'     => __( 'Home', 'crispy-seo' ),
			'item'     => home_url( '/' ),
		];

		if ( is_singular() ) {
			$post = get_post();

			// Add category for posts.
			if ( $post->post_type === 'post' ) {
				$categories = get_the_category( $post->ID );
				if ( ! empty( $categories ) ) {
					$category = $categories[0];
					$items[]  = [
						'@type'    => 'ListItem',
						'position' => $position++,
						'name'     => $category->name,
						'item'     => get_category_link( $category->term_id ),
					];
				}
			}

			// Current page.
			$items[] = [
				'@type'    => 'ListItem',
				'position' => $position,
				'name'     => get_the_title( $post->ID ),
			];
		} elseif ( is_category() ) {
			$category = get_queried_object();
			$items[]  = [
				'@type'    => 'ListItem',
				'position' => $position,
				'name'     => $category->name,
			];
		} elseif ( is_tag() ) {
			$tag     = get_queried_object();
			$items[] = [
				'@type'    => 'ListItem',
				'position' => $position,
				'name'     => $tag->name,
			];
		} elseif ( is_author() ) {
			$author  = get_queried_object();
			$items[] = [
				'@type'    => 'ListItem',
				'position' => $position,
				'name'     => $author->display_name,
			];
		}

		if ( count( $items ) < 2 ) {
			return null;
		}

		return [
			'@context'        => 'https://schema.org',
			'@type'           => 'BreadcrumbList',
			'itemListElement' => $items,
		];
	}

	/**
	 * Generate post-specific schema.
	 *
	 * @return array<string, mixed>|null
	 */
	private function generatePostSchema(): ?array {
		$post = get_post();
		if ( ! $post ) {
			return null;
		}

		$schemaType = get_post_meta( $post->ID, '_crispy_seo_schema_type', true );
		if ( empty( $schemaType ) ) {
			$schemaType = get_option( 'crispy_seo_default_schema_type', 'Article' );
		}

		$method = 'generate' . str_replace( [ '/', ' ' ], '', $schemaType ) . 'Schema';

		if ( method_exists( $this, $method ) ) {
			return $this->$method( $post );
		}

		// Default to Article.
		return $this->generateArticleSchema( $post );
	}

	/**
	 * Generate Article schema.
	 *
	 * @param \WP_Post $post Post object.
	 * @return array<string, mixed>
	 */
	private function generateArticleSchema( \WP_Post $post ): array {
		$schema = [
			'@context'         => 'https://schema.org',
			'@type'            => 'Article',
			'headline'         => get_the_title( $post->ID ),
			'description'      => $this->getDescription( $post ),
			'url'              => get_permalink( $post->ID ),
			'datePublished'    => get_the_date( 'c', $post ),
			'dateModified'     => get_the_modified_date( 'c', $post ),
			'author'           => $this->getAuthorSchema( $post ),
			'publisher'        => $this->getPublisherSchema(),
			'mainEntityOfPage' => [
				'@type' => 'WebPage',
				'@id'   => get_permalink( $post->ID ),
			],
		];

		// Add featured image.
		if ( has_post_thumbnail( $post->ID ) ) {
			$imageId  = get_post_thumbnail_id( $post->ID );
			$imageSrc = wp_get_attachment_image_src( $imageId, 'full' );
			if ( $imageSrc ) {
				$schema['image'] = [
					'@type'  => 'ImageObject',
					'url'    => $imageSrc[0],
					'width'  => $imageSrc[1],
					'height' => $imageSrc[2],
				];
			}
		}

		// Add word count.
		$content = $post->post_content;
		if ( has_post_meta( $post->ID, '_markdown_content' ) ) {
			$content = get_post_meta( $post->ID, '_markdown_content', true );
		}
		$schema['wordCount'] = str_word_count( wp_strip_all_tags( $content ) );

		return $schema;
	}

	/**
	 * Generate BlogPosting schema.
	 *
	 * @param \WP_Post $post Post object.
	 * @return array<string, mixed>
	 */
	private function generateBlogPostingSchema( \WP_Post $post ): array {
		$schema          = $this->generateArticleSchema( $post );
		$schema['@type'] = 'BlogPosting';
		return $schema;
	}

	/**
	 * Generate NewsArticle schema.
	 *
	 * @param \WP_Post $post Post object.
	 * @return array<string, mixed>
	 */
	private function generateNewsArticleSchema( \WP_Post $post ): array {
		$schema          = $this->generateArticleSchema( $post );
		$schema['@type'] = 'NewsArticle';
		return $schema;
	}

	/**
	 * Generate TechArticle schema.
	 *
	 * @param \WP_Post $post Post object.
	 * @return array<string, mixed>
	 */
	private function generateTechArticleSchema( \WP_Post $post ): array {
		$schema          = $this->generateArticleSchema( $post );
		$schema['@type'] = 'TechArticle';
		return $schema;
	}

	/**
	 * Generate Person schema.
	 *
	 * @param \WP_Post $post Post object.
	 * @return array<string, mixed>
	 */
	private function generatePersonSchema( \WP_Post $post ): array {
		$author = get_user_by( 'id', $post->post_author );

		return [
			'@context'    => 'https://schema.org',
			'@type'       => 'Person',
			'name'        => $author->display_name,
			'url'         => get_author_posts_url( $author->ID ),
			'description' => get_the_author_meta( 'description', $author->ID ),
		];
	}

	/**
	 * Generate FAQPage schema.
	 *
	 * @param \WP_Post $post Post object.
	 * @return array<string, mixed>
	 */
	private function generateFAQPageSchema( \WP_Post $post ): array {
		// Parse FAQ from content.
		$content = $post->post_content;
		if ( has_post_meta( $post->ID, '_markdown_content' ) ) {
			$content = get_post_meta( $post->ID, '_markdown_content', true );
		}

		$faqs = $this->parseFAQFromContent( $content );

		return [
			'@context'   => 'https://schema.org',
			'@type'      => 'FAQPage',
			'mainEntity' => $faqs,
		];
	}

	/**
	 * Parse FAQ items from content.
	 *
	 * @param string $content Post content.
	 * @return array<array<string, mixed>>
	 */
	private function parseFAQFromContent( string $content ): array {
		$faqs = [];

		// Look for Q: and A: patterns or ## headings followed by content.
		preg_match_all( '/##\s+(.+?)\n\n(.+?)(?=\n##|\z)/s', $content, $matches, PREG_SET_ORDER );

		foreach ( $matches as $match ) {
			$faqs[] = [
				'@type'          => 'Question',
				'name'           => trim( $match[1] ),
				'acceptedAnswer' => [
					'@type' => 'Answer',
					'text'  => trim( $match[2] ),
				],
			];
		}

		return $faqs;
	}

	/**
	 * Generate HowTo schema.
	 *
	 * @param \WP_Post $post Post object.
	 * @return array<string, mixed>
	 */
	private function generateHowToSchema( \WP_Post $post ): array {
		$content = $post->post_content;
		if ( has_post_meta( $post->ID, '_markdown_content' ) ) {
			$content = get_post_meta( $post->ID, '_markdown_content', true );
		}

		$steps = $this->parseStepsFromContent( $content );

		return [
			'@context'    => 'https://schema.org',
			'@type'       => 'HowTo',
			'name'        => get_the_title( $post->ID ),
			'description' => $this->getDescription( $post ),
			'step'        => $steps,
		];
	}

	/**
	 * Parse steps from content.
	 *
	 * @param string $content Post content.
	 * @return array<array<string, mixed>>
	 */
	private function parseStepsFromContent( string $content ): array {
		$steps    = [];
		$position = 1;

		// Look for numbered list items or ## Step headings.
		preg_match_all( '/^\d+\.\s+(.+)$/m', $content, $matches );

		foreach ( $matches[1] as $step ) {
			$steps[] = [
				'@type'    => 'HowToStep',
				'position' => $position++,
				'text'     => trim( $step ),
			];
		}

		return $steps;
	}

	/**
	 * Get description for post.
	 *
	 * @param \WP_Post $post Post object.
	 */
	private function getDescription( \WP_Post $post ): string {
		$description = get_post_meta( $post->ID, '_crispy_seo_description', true );
		if ( ! empty( $description ) ) {
			return $description;
		}

		$content = $post->post_content;
		if ( has_post_meta( $post->ID, '_markdown_content' ) ) {
			$content = get_post_meta( $post->ID, '_markdown_content', true );
		}

		$text = wp_strip_all_tags( $content );
		$text = preg_replace( '/\s+/', ' ', $text );
		$text = trim( $text );

		if ( strlen( $text ) > 160 ) {
			$text = substr( $text, 0, 157 ) . '...';
		}

		return $text;
	}

	/**
	 * Get author schema.
	 *
	 * @param \WP_Post $post Post object.
	 * @return array<string, mixed>
	 */
	private function getAuthorSchema( \WP_Post $post ): array {
		$author = get_user_by( 'id', $post->post_author );

		return [
			'@type' => 'Person',
			'name'  => $author->display_name,
			'url'   => get_author_posts_url( $author->ID ),
		];
	}

	/**
	 * Get publisher schema.
	 *
	 * @return array<string, mixed>
	 */
	private function getPublisherSchema(): array {
		$orgName = get_option( 'crispy_seo_organization_name', get_bloginfo( 'name' ) );
		$orgLogo = get_option( 'crispy_seo_organization_logo', '' );

		$publisher = [
			'@type' => 'Organization',
			'name'  => $orgName,
			'url'   => home_url( '/' ),
		];

		if ( ! empty( $orgLogo ) ) {
			$publisher['logo'] = [
				'@type' => 'ImageObject',
				'url'   => $orgLogo,
			];
		}

		return $publisher;
	}
}
