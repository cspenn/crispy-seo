<?php
/**
 * Analytics Manager
 *
 * @package CrispySEO\Analytics
 */

declare(strict_types=1);

namespace CrispySEO\Analytics;

/**
 * Manages analytics integrations.
 */
class AnalyticsManager {

	/**
	 * Output analytics scripts.
	 */
	public function output(): void {
		// Don't track admins or logged-in users if configured.
		if ( is_user_logged_in() && ! $this->shouldTrackLoggedIn() ) {
			return;
		}

		// Don't track in admin or preview mode.
		if ( is_admin() || is_preview() ) {
			return;
		}

		$this->outputGA4();
		$this->outputPlausible();
		$this->outputFathom();
		$this->outputMatomo();
	}

	/**
	 * Check if logged-in users should be tracked.
	 */
	private function shouldTrackLoggedIn(): bool {
		return (bool) get_option( 'crispy_seo_track_logged_in', false );
	}

	/**
	 * Output Google Analytics 4.
	 */
	private function outputGA4(): void {
		$measurementId = get_option( 'crispy_seo_ga4_id', '' );

		if ( empty( $measurementId ) ) {
			return;
		}

		// Validate measurement ID format.
		if ( ! preg_match( '/^G-[A-Z0-9]+$/', $measurementId ) ) {
			return;
		}

		?>
<!-- Google Analytics 4 - CrispySEO -->
<script async src="https://www.googletagmanager.com/gtag/js?id=<?php echo esc_attr( $measurementId ); ?>"></script>
<script>
window.dataLayer = window.dataLayer || [];
function gtag(){dataLayer.push(arguments);}
gtag('js', new Date());
gtag('config', '<?php echo esc_js( $measurementId ); ?>'<?php echo $this->getGA4Config(); ?>);
</script>
		<?php
	}

	/**
	 * Get GA4 config options.
	 */
	private function getGA4Config(): string {
		$config = [];

		// Anonymize IP (required in some jurisdictions).
		if ( get_option( 'crispy_seo_ga4_anonymize_ip', true ) ) {
			$config['anonymize_ip'] = true;
		}

		// Disable advertising features.
		if ( ! get_option( 'crispy_seo_ga4_advertising', false ) ) {
			$config['allow_google_signals']             = false;
			$config['allow_ad_personalization_signals'] = false;
		}

		if ( empty( $config ) ) {
			return '';
		}

		return ', ' . wp_json_encode( $config );
	}

	/**
	 * Output Plausible Analytics.
	 */
	private function outputPlausible(): void {
		$domain = get_option( 'crispy_seo_plausible_domain', '' );

		if ( empty( $domain ) ) {
			return;
		}

		$scriptUrl = 'https://plausible.io/js/script.js';

		// Custom domain for self-hosted.
		$customDomain = get_option( 'crispy_seo_plausible_custom_domain', '' );
		if ( ! empty( $customDomain ) ) {
			$scriptUrl = rtrim( $customDomain, '/' ) . '/js/script.js';
		}

		// Additional script extensions.
		$extensions = [];

		if ( get_option( 'crispy_seo_plausible_outbound_links', false ) ) {
			$extensions[] = 'outbound-links';
		}

		if ( get_option( 'crispy_seo_plausible_file_downloads', false ) ) {
			$extensions[] = 'file-downloads';
		}

		if ( ! empty( $extensions ) ) {
			$scriptUrl = str_replace( '.js', '.' . implode( '.', $extensions ) . '.js', $scriptUrl );
		}

		?>
<!-- Plausible Analytics - CrispySEO -->
<script defer data-domain="<?php echo esc_attr( $domain ); ?>" src="<?php echo esc_url( $scriptUrl ); ?>"></script>
		<?php
	}

	/**
	 * Output Fathom Analytics.
	 */
	private function outputFathom(): void {
		$siteId = get_option( 'crispy_seo_fathom_site_id', '' );

		if ( empty( $siteId ) ) {
			return;
		}

		// Validate site ID format.
		if ( ! preg_match( '/^[A-Z]{8}$/', $siteId ) ) {
			return;
		}

		$scriptUrl = 'https://cdn.usefathom.com/script.js';

		// Custom domain for self-hosted.
		$customDomain = get_option( 'crispy_seo_fathom_custom_domain', '' );
		if ( ! empty( $customDomain ) ) {
			$scriptUrl = rtrim( $customDomain, '/' ) . '/script.js';
		}

		?>
<!-- Fathom Analytics - CrispySEO -->
<script src="<?php echo esc_url( $scriptUrl ); ?>" data-site="<?php echo esc_attr( $siteId ); ?>" defer></script>
		<?php
	}

	/**
	 * Output Matomo Analytics.
	 */
	private function outputMatomo(): void {
		$matomoUrl = get_option( 'crispy_seo_matomo_url', '' );
		$siteId    = get_option( 'crispy_seo_matomo_site_id', '' );

		if ( empty( $matomoUrl ) || empty( $siteId ) ) {
			return;
		}

		// Ensure URL ends with /
		$matomoUrl = rtrim( $matomoUrl, '/' ) . '/';

		?>
<!-- Matomo Analytics - CrispySEO -->
<script>
var _paq = window._paq = window._paq || [];
_paq.push(['trackPageView']);
_paq.push(['enableLinkTracking']);
(function() {
	var u="<?php echo esc_url( $matomoUrl ); ?>";
	_paq.push(['setTrackerUrl', u+'matomo.php']);
	_paq.push(['setSiteId', '<?php echo esc_js( $siteId ); ?>']);
	var d=document, g=d.createElement('script'), s=d.getElementsByTagName('script')[0];
	g.async=true; g.src=u+'matomo.js'; s.parentNode.insertBefore(g,s);
})();
</script>
<noscript><img src="<?php echo esc_url( $matomoUrl ); ?>matomo.php?idsite=<?php echo esc_attr( $siteId ); ?>&amp;rec=1" style="border:0" alt="" /></noscript>
		<?php
	}

	/**
	 * Get available analytics providers.
	 *
	 * @return array<string, array{name: string, configured: bool}>
	 */
	public function getProviders(): array {
		return [
			'ga4'       => [
				'name'       => 'Google Analytics 4',
				'configured' => ! empty( get_option( 'crispy_seo_ga4_id' ) ),
			],
			'plausible' => [
				'name'       => 'Plausible Analytics',
				'configured' => ! empty( get_option( 'crispy_seo_plausible_domain' ) ),
			],
			'fathom'    => [
				'name'       => 'Fathom Analytics',
				'configured' => ! empty( get_option( 'crispy_seo_fathom_site_id' ) ),
			],
			'matomo'    => [
				'name'       => 'Matomo',
				'configured' => ! empty( get_option( 'crispy_seo_matomo_url' ) ) && ! empty( get_option( 'crispy_seo_matomo_site_id' ) ),
			],
		];
	}
}
