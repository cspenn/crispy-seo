<?php
/**
 * Social Share Buttons
 *
 * @package CrispySEO\Social
 */

declare(strict_types=1);

namespace CrispySEO\Social;

/**
 * Manages social sharing buttons.
 */
class ShareButtons {

	/**
	 * Available share networks.
	 */
	public const NETWORKS = [
		'facebook',
		'twitter',
		'linkedin',
		'pinterest',
		'reddit',
		'email',
		'copy',
	];

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_shortcode( 'crispy_share', [ $this, 'renderShortcode' ] );
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueueAssets' ] );

		// Add share buttons to content if enabled.
		if ( get_option( 'crispy_seo_auto_share_buttons', false ) ) {
			add_filter( 'the_content', [ $this, 'appendToContent' ], 99 );
		}
	}

	/**
	 * Enqueue share button assets.
	 */
	public function enqueueAssets(): void {
		wp_register_style(
			'crispy-share-buttons',
			CRISPY_SEO_URL . 'assets/css/share-buttons.css',
			[],
			CRISPY_SEO_VERSION
		);

		wp_register_script(
			'crispy-share-buttons',
			CRISPY_SEO_URL . 'assets/js/share-buttons.js',
			[],
			CRISPY_SEO_VERSION,
			true
		);
	}

	/**
	 * Render share buttons shortcode.
	 *
	 * @param array<string, string> $atts Shortcode attributes.
	 */
	public function renderShortcode( array $atts = [] ): string {
		$atts = shortcode_atts(
			[
				'networks'    => implode( ',', self::NETWORKS ),
				'style'       => 'default',
				'show_counts' => 'false',
				'title'       => '',
				'url'         => '',
				'image'       => '',
			],
			$atts
		);

		return $this->render(
			[
				'networks'   => array_map( 'trim', explode( ',', $atts['networks'] ) ),
				'style'      => $atts['style'],
				'showCounts' => $atts['show_counts'] === 'true',
				'title'      => $atts['title'],
				'url'        => $atts['url'],
				'image'      => $atts['image'],
			]
		);
	}

	/**
	 * Render share buttons.
	 *
	 * @param array<string, mixed> $options Render options.
	 */
	public function render( array $options = [] ): string {
		$defaults = [
			'networks'   => $this->getEnabledNetworks(),
			'style'      => get_option( 'crispy_seo_share_style', 'default' ),
			'showCounts' => get_option( 'crispy_seo_show_share_counts', false ),
			'title'      => '',
			'url'        => '',
			'image'      => '',
		];

		$options = array_merge( $defaults, $options );

		// Get current post data if not provided.
		$post = get_post();
		if ( $post ) {
			if ( empty( $options['title'] ) ) {
				$options['title'] = get_the_title( $post );
			}
			if ( empty( $options['url'] ) ) {
				$options['url'] = get_permalink( $post );
			}
			if ( empty( $options['image'] ) && has_post_thumbnail( $post ) ) {
				$options['image'] = get_the_post_thumbnail_url( $post, 'large' );
			}
		}

		// Enqueue assets.
		wp_enqueue_style( 'crispy-share-buttons' );
		wp_enqueue_script( 'crispy-share-buttons' );

		$output  = '<div class="crispy-share-buttons crispy-share-buttons--' . esc_attr( $options['style'] ) . '">';
		$output .= '<span class="crispy-share-buttons__label">' . esc_html__( 'Share:', 'crispy-seo' ) . '</span>';
		$output .= '<div class="crispy-share-buttons__list">';

		foreach ( $options['networks'] as $network ) {
			if ( in_array( $network, self::NETWORKS, true ) ) {
				$output .= $this->renderButton( $network, $options );
			}
		}

		$output .= '</div>';
		$output .= '</div>';

		return $output;
	}

	/**
	 * Render individual share button.
	 *
	 * @param string               $network Network name.
	 * @param array<string, mixed> $options Share options.
	 */
	private function renderButton( string $network, array $options ): string {
		$url   = esc_url( $options['url'] );
		$title = esc_attr( $options['title'] );
		$image = esc_url( $options['image'] );

		$shareUrl = $this->getShareUrl( $network, $url, $title, $image );
		$label    = $this->getNetworkLabel( $network );
		$icon     = $this->getNetworkIcon( $network );

		$buttonClass = 'crispy-share-button crispy-share-button--' . $network;

		if ( $network === 'copy' ) {
			return sprintf(
				'<button type="button" class="%s" data-url="%s" aria-label="%s">%s<span class="crispy-share-button__label">%s</span></button>',
				esc_attr( $buttonClass ),
				esc_attr( $url ),
				/* translators: %s: network name */
				sprintf( esc_attr__( 'Share via %s', 'crispy-seo' ), $label ),
				$icon,
				esc_html( $label )
			);
		}

		return sprintf(
			'<a href="%s" class="%s" target="_blank" rel="noopener noreferrer" aria-label="%s">%s<span class="crispy-share-button__label">%s</span></a>',
			esc_url( $shareUrl ),
			esc_attr( $buttonClass ),
			/* translators: %s: network name */
			sprintf( esc_attr__( 'Share via %s', 'crispy-seo' ), $label ),
			$icon,
			esc_html( $label )
		);
	}

	/**
	 * Get share URL for network.
	 *
	 * @param string $network Network name.
	 * @param string $url     URL to share.
	 * @param string $title   Title to share.
	 * @param string $image   Image URL.
	 */
	private function getShareUrl( string $network, string $url, string $title, string $image ): string {
		$encodedUrl   = rawurlencode( $url );
		$encodedTitle = rawurlencode( $title );

		switch ( $network ) {
			case 'facebook':
				return "https://www.facebook.com/sharer/sharer.php?u={$encodedUrl}";

			case 'twitter':
				return "https://twitter.com/intent/tweet?url={$encodedUrl}&text={$encodedTitle}";

			case 'linkedin':
				return "https://www.linkedin.com/sharing/share-offsite/?url={$encodedUrl}";

			case 'pinterest':
				$encodedImage = rawurlencode( $image );
				return "https://pinterest.com/pin/create/button/?url={$encodedUrl}&media={$encodedImage}&description={$encodedTitle}";

			case 'reddit':
				return "https://reddit.com/submit?url={$encodedUrl}&title={$encodedTitle}";

			case 'email':
				return "mailto:?subject={$encodedTitle}&body={$encodedUrl}";

			default:
				return '';
		}
	}

	/**
	 * Get network label.
	 *
	 * @param string $network Network name.
	 */
	private function getNetworkLabel( string $network ): string {
		$labels = [
			'facebook'  => __( 'Facebook', 'crispy-seo' ),
			'twitter'   => __( 'X (Twitter)', 'crispy-seo' ),
			'linkedin'  => __( 'LinkedIn', 'crispy-seo' ),
			'pinterest' => __( 'Pinterest', 'crispy-seo' ),
			'reddit'    => __( 'Reddit', 'crispy-seo' ),
			'email'     => __( 'Email', 'crispy-seo' ),
			'copy'      => __( 'Copy Link', 'crispy-seo' ),
		];

		return $labels[ $network ] ?? ucfirst( $network );
	}

	/**
	 * Get network icon SVG.
	 *
	 * @param string $network Network name.
	 */
	private function getNetworkIcon( string $network ): string {
		$icons = [
			'facebook'  => '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>',
			'twitter'   => '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg>',
			'linkedin'  => '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433c-1.144 0-2.063-.926-2.063-2.065 0-1.138.92-2.063 2.063-2.063 1.14 0 2.064.925 2.064 2.063 0 1.139-.925 2.065-2.064 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z"/></svg>',
			'pinterest' => '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M12.017 0C5.396 0 .029 5.367.029 11.987c0 5.079 3.158 9.417 7.618 11.162-.105-.949-.199-2.403.041-3.439.219-.937 1.406-5.957 1.406-5.957s-.359-.72-.359-1.781c0-1.663.967-2.911 2.168-2.911 1.024 0 1.518.769 1.518 1.688 0 1.029-.653 2.567-.992 3.992-.285 1.193.6 2.165 1.775 2.165 2.128 0 3.768-2.245 3.768-5.487 0-2.861-2.063-4.869-5.008-4.869-3.41 0-5.409 2.562-5.409 5.199 0 1.033.394 2.143.889 2.741.099.12.112.225.085.345-.09.375-.293 1.199-.334 1.363-.053.225-.172.271-.401.165-1.495-.69-2.433-2.878-2.433-4.646 0-3.776 2.748-7.252 7.92-7.252 4.158 0 7.392 2.967 7.392 6.923 0 4.135-2.607 7.462-6.233 7.462-1.214 0-2.354-.629-2.758-1.379l-.749 2.848c-.269 1.045-1.004 2.352-1.498 3.146 1.123.345 2.306.535 3.55.535 6.607 0 11.985-5.365 11.985-11.987C23.97 5.39 18.592.026 11.985.026L12.017 0z"/></svg>',
			'reddit'    => '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M12 0A12 12 0 0 0 0 12a12 12 0 0 0 12 12 12 12 0 0 0 12-12A12 12 0 0 0 12 0zm5.01 4.744c.688 0 1.25.561 1.25 1.249a1.25 1.25 0 0 1-2.498.056l-2.597-.547-.8 3.747c1.824.07 3.48.632 4.674 1.488.308-.309.73-.491 1.207-.491.968 0 1.754.786 1.754 1.754 0 .716-.435 1.333-1.01 1.614a3.111 3.111 0 0 1 .042.52c0 2.694-3.13 4.87-7.004 4.87-3.874 0-7.004-2.176-7.004-4.87 0-.183.015-.366.043-.534A1.748 1.748 0 0 1 4.028 12c0-.968.786-1.754 1.754-1.754.463 0 .898.196 1.207.49 1.207-.883 2.878-1.43 4.744-1.487l.885-4.182a.342.342 0 0 1 .14-.197.35.35 0 0 1 .238-.042l2.906.617a1.214 1.214 0 0 1 1.108-.701zM9.25 12C8.561 12 8 12.562 8 13.25c0 .687.561 1.248 1.25 1.248.687 0 1.248-.561 1.248-1.249 0-.688-.561-1.249-1.249-1.249zm5.5 0c-.687 0-1.248.561-1.248 1.25 0 .687.561 1.248 1.249 1.248.688 0 1.249-.561 1.249-1.249 0-.687-.562-1.249-1.25-1.249zm-5.466 3.99a.327.327 0 0 0-.231.094.33.33 0 0 0 0 .463c.842.842 2.484.913 2.961.913.477 0 2.105-.056 2.961-.913a.361.361 0 0 0 .029-.463.33.33 0 0 0-.464 0c-.547.533-1.684.73-2.512.73-.828 0-1.979-.196-2.512-.73a.326.326 0 0 0-.232-.095z"/></svg>',
			'email'     => '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M20 4H4c-1.1 0-1.99.9-1.99 2L2 18c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 4l-8 5-8-5V6l8 5 8-5v2z"/></svg>',
			'copy'      => '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M16 1H4c-1.1 0-2 .9-2 2v14h2V3h12V1zm3 4H8c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h11c1.1 0 2-.9 2-2V7c0-1.1-.9-2-2-2zm0 16H8V7h11v14z"/></svg>',
		];

		return $icons[ $network ] ?? '';
	}

	/**
	 * Get enabled networks.
	 *
	 * @return array<string>
	 */
	private function getEnabledNetworks(): array {
		$enabled = get_option( 'crispy_seo_share_networks', [] );

		if ( empty( $enabled ) ) {
			return [ 'facebook', 'twitter', 'linkedin', 'email' ];
		}

		return $enabled;
	}

	/**
	 * Append share buttons to content.
	 *
	 * @param string $content Post content.
	 */
	public function appendToContent( string $content ): string {
		if ( ! is_singular() || ! in_the_loop() || ! is_main_query() ) {
			return $content;
		}

		$position = get_option( 'crispy_seo_share_position', 'after' );

		if ( $position === 'before' ) {
			return $this->render() . $content;
		} elseif ( $position === 'both' ) {
			return $this->render() . $content . $this->render();
		}

		return $content . $this->render();
	}
}
