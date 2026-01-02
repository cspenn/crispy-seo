=== CrispySEO ===
Contributors: christopherspenn
Tags: seo, schema, redirects, analytics, sitemap, internal links, meta tags, json-ld
Requires at least: 6.6
Tested up to: 6.9
Requires PHP: 8.1
Stable tag: 2.0.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Comprehensive SEO plugin with meta tags, JSON-LD schema, sitemaps, redirects, internal link management, image optimization, and analytics integration.

== Description ==

CrispySEO is a comprehensive SEO plugin designed for performance and simplicity. It provides all the essential SEO features without bloat or external API dependencies.

**Features:**

* **Meta Tags** - Title, description, Open Graph, Twitter Cards
* **JSON-LD Schema** - Article, Organization, Person, FAQ, HowTo, and more
* **XML Sitemaps** - Auto-generated with customizable post types
* **Redirect Manager** - 301, 302, 307, 308, 410, 451 redirects with regex support
* **Internal Link Manager** - Automatic keyword-based internal linking
* **Image Optimization** - Local compression using GD/Imagick (no external APIs)
* **Media Replacement** - Replace files without breaking links
* **Analytics Integration** - GA4, Plausible, Fathom, Matomo support
* **WP-CLI Commands** - Full CLI support for automation

**Performance:**

* No external API calls for image optimization
* Efficient database queries with custom tables
* Transient caching for frequently accessed data
* Background processing for bulk operations

== Installation ==

1. Upload the `crispy-seo` folder to `/wp-content/plugins/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Configure settings under 'Settings > CrispySEO'

== Frequently Asked Questions ==

= Does this plugin require any external services? =

No. All features work locally without external API dependencies.

= Is this compatible with other SEO plugins? =

CrispySEO will detect other SEO plugins and can import data from Rank Math and Internal Link Juicer.

= What PHP version is required? =

PHP 8.1 or higher is required.

== Changelog ==

= 2.0.0 =
* Added Enhanced Redirect Manager with regex support
* Added Internal Link Manager with automatic linking
* Added Image Optimization with WebP conversion
* Added Media Replacement functionality
* Added Database Search & Replace tool
* Added WP-CLI commands for all features
* Added migration tools for Rank Math and Internal Link Juicer

= 1.0.0 =
* Initial release with meta tags, schema, sitemaps, and analytics

== Upgrade Notice ==

= 2.0.0 =
Major update with redirect manager, internal links, and image optimization. Database tables are created automatically on upgrade.
