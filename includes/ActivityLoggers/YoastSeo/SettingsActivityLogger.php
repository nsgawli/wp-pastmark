<?php // phpcs:ignore WordPress.Files.FileName.NotHyphenatedLowercase

namespace Pastmark\ActivityLoggers\YoastSeo;

use Pastmark\ActivityLoggers\AbstractLogger;
use Pastmark\Constants\Actions;
use Pastmark\Constants\Events;
use Pastmark\Constants\Severity;
use Pastmark\Utils\ContentDiffer;

defined( 'ABSPATH' ) || exit;

/**
 * Yoast SEO global-settings activity logger (PM-166; expanded to granular
 * per-setting actions on real user request after comparing against WP
 * Activity Log's own Yoast alert set - see `sprint-10.md`'s PM-166 notes).
 *
 * Confirmed against the real installed plugin's own option-registry classes
 * (`inc/options/class-wpseo-option-*.php`): Yoast stores its configuration
 * across three standalone options - `wpseo` (General), `wpseo_titles`
 * (Titles & Metas), `wpseo_social` (Social). A fourth, `wpseo_ms`, is
 * multisite-network-only and out of scope (this environment isn't
 * multisite, so it can't be live-verified - see the class-level "Deferred"
 * notes below). Hooks WordPress's own dynamic `update_option_{$option}`
 * action per option - fires `($old_value, $new_value, $option)`.
 *
 * Rather than one full-array diff per option (this ticket's original
 * shape), every save is now compared key-by-key against `STATIC_TRACKED_KEYS`
 * (fixed setting names) and `DYNAMIC_POST_TYPE_KEYS`/`DYNAMIC_TAXONOMY_KEYS`
 * (setting-name *prefixes* applied to every real public post type/taxonomy),
 * so a single option save that touches several unrelated settings (e.g. the
 * title separator and an author-archive toggle in the same "Save changes"
 * click) produces one distinct, correctly-labeled row per changed setting -
 * matching WP Activity Log's own per-alert granularity for the equivalent
 * settings screens, confirmed against its real sensor source
 * (`class-yoast-seo-sensor.php`/`class-yoast-custom-alerts.php`).
 *
 * Any changed key not covered by either map still falls back to one
 * aggregated `Actions::UPDATE` row for that option - the ticket's original
 * behavior, kept as a safety net so a setting this logger doesn't yet name
 * explicitly (a future Yoast release, a Premium-only field) still logs
 * something instead of being silently dropped.
 *
 * **Deliberately not built, disclosed rather than silently dropped:**
 * - Multisite network-level settings (WP Activity Log's alert IDs
 *   8838-8840, 8842, 8843 - `wpseo_ms`/`update_site_option`-driven). This
 *   environment is single-site; building against a mechanism with no way
 *   to live-verify here would violate this project's own standing
 *   "grounded in a real, verified environment" discipline. Revisit if/when
 *   a multisite environment is available.
 * - Yoast **Premium**'s own redirect-manager settings (alert IDs 8855-8858 -
 *   `wpseo-premium-redirects-export-plain/-regex`/`wpseo_redirect` options).
 *   Only free Yoast SEO is installed in this environment; these options
 *   don't exist without Premium, so there's nothing to verify against. Not
 *   to be confused with PM-168's separate "Redirection" plugin integration.
 * - WP Activity Log's own dead/superseded alert IDs (8815-8819, 8821, 8828,
 *   8844, 8846 - confirmed, by reading their real sensor code, to no longer
 *   fire at all; they were replaced by a single consolidated alert). This
 *   logger implements that live replacement (`Actions::SEO_FEATURE_TOGGLE`,
 *   matching WSAL's current `8859`) instead of resurrecting retired,
 *   unreachable alert-specific logic.
 * - The site tagline (`blogdescription`, WSAL's alert 8865) - already
 *   logged by core `WPSettingsActivityLogger` (it's a core WP option, not
 *   a Yoast one); adding a second, Yoast-specific event for the same save
 *   would be exactly the class of duplicate-logging bug PM-154 found for
 *   ACF's custom post types.
 * - One deliberate improvement over WP Activity Log, not a gap: its own
 *   per-post-type/per-taxonomy settings (search-visibility, title/meta-
 *   description templates, meta-box toggles) only actually fire for the
 *   `page` post type and `category` taxonomy - a real limitation in its own
 *   `switch`/`case` matching, confirmed by reading its code, despite it
 *   computing the diff for every registered public post type/taxonomy.
 *   `DYNAMIC_POST_TYPE_KEYS`/`DYNAMIC_TAXONOMY_KEYS` below generalize to
 *   every public post type/taxonomy instead, since this data-driven design
 *   costs nothing extra to do so.
 */
class SettingsActivityLogger extends AbstractLogger {

	/**
	 * {@inheritDoc}
	 *
	 * @var string
	 */
	protected $integration = 'yoast-seo';

	/**
	 * Yoast's own global-settings options, mapped to a human-readable label
	 * matching the corresponding admin screen name.
	 *
	 * @var array<string, string>
	 */
	private const TRACKED_OPTIONS = array(
		'wpseo'        => 'General',
		'wpseo_titles' => 'Titles & Metas',
		'wpseo_social' => 'Social',
	);

	/**
	 * Fixed (non-per-type) setting keys tracked per option, each with its
	 * own `Actions` constant, a short label, and a severity - grounded in
	 * WP Activity Log's real Yoast sensor source (`class-yoast-seo-
	 * sensor.php`), confirmed key-by-key rather than assumed.
	 *
	 * @var array<string, array<string, array{action: string, label: string, severity: string}>>
	 */
	private const STATIC_TRACKED_KEYS = array(
		'wpseo'        => array(
			'disableadvanced_meta'           => array(
				'action'   => Actions::SEO_AUTHOR_ADVANCED_META_TOGGLE,
				'label'    => 'Author advanced/schema settings',
				'severity' => Severity::INFO,
			),
			'tracking'                       => array(
				'action'   => Actions::SEO_USAGE_TRACKING_TOGGLE,
				'label'    => 'Usage tracking',
				'severity' => Severity::WARNING,
			),
			'baiduverify'                    => array(
				'action'   => Actions::SEO_VERIFICATION_CODE_CHANGE,
				'label'    => 'Baidu verification code',
				'severity' => Severity::WARNING,
			),
			'googleverify'                   => array(
				'action'   => Actions::SEO_VERIFICATION_CODE_CHANGE,
				'label'    => 'Google verification code',
				'severity' => Severity::WARNING,
			),
			'msverify'                       => array(
				'action'   => Actions::SEO_VERIFICATION_CODE_CHANGE,
				'label'    => 'Bing (MS) verification code',
				'severity' => Severity::WARNING,
			),
			'yandexverify'                   => array(
				'action'   => Actions::SEO_VERIFICATION_CODE_CHANGE,
				'label'    => 'Yandex verification code',
				'severity' => Severity::WARNING,
			),
			'onpage_indexability'            => array(
				'action'   => Actions::SEO_INTEGRATION_TOGGLE,
				'label'    => 'Ryte indexability integration',
				'severity' => Severity::WARNING,
			),
			'ryte_indexability'              => array(
				'action'   => Actions::SEO_INTEGRATION_TOGGLE,
				'label'    => 'Ryte indexability integration',
				'severity' => Severity::WARNING,
			),
			'semrush_integration_active'     => array(
				'action'   => Actions::SEO_INTEGRATION_TOGGLE,
				'label'    => 'Semrush integration',
				'severity' => Severity::WARNING,
			),
			'zapier_integration_active'      => array(
				'action'   => Actions::SEO_INTEGRATION_TOGGLE,
				'label'    => 'Zapier integration',
				'severity' => Severity::WARNING,
			),
			'algolia_integration_active'     => array(
				'action'   => Actions::SEO_INTEGRATION_TOGGLE,
				'label'    => 'Algolia integration',
				'severity' => Severity::WARNING,
			),
			'wincher_integration_active'     => array(
				'action'   => Actions::SEO_INTEGRATION_TOGGLE,
				'label'    => 'Wincher integration',
				'severity' => Severity::WARNING,
			),
			'keyword_analysis_active'        => array(
				'action'   => Actions::SEO_FEATURE_TOGGLE,
				'label'    => 'SEO analysis',
				'severity' => Severity::INFO,
			),
			'content_analysis_active'        => array(
				'action'   => Actions::SEO_FEATURE_TOGGLE,
				'label'    => 'Readability analysis',
				'severity' => Severity::INFO,
			),
			'enable_cornerstone_content'     => array(
				'action'   => Actions::SEO_FEATURE_TOGGLE,
				'label'    => 'Cornerstone content feature',
				'severity' => Severity::INFO,
			),
			'enable_text_link_counter'       => array(
				'action'   => Actions::SEO_FEATURE_TOGGLE,
				'label'    => 'Text link counter',
				'severity' => Severity::INFO,
			),
			'enable_xml_sitemap'             => array(
				'action'   => Actions::SEO_FEATURE_TOGGLE,
				'label'    => 'XML sitemaps',
				'severity' => Severity::INFO,
			),
			'enable_admin_bar_menu'          => array(
				'action'   => Actions::SEO_FEATURE_TOGGLE,
				'label'    => 'Admin bar menu',
				'severity' => Severity::INFO,
			),
			'enable_headless_rest_endpoints' => array(
				'action'   => Actions::SEO_FEATURE_TOGGLE,
				'label'    => 'REST API head endpoint',
				'severity' => Severity::INFO,
			),
			'enable_index_now'               => array(
				'action'   => Actions::SEO_FEATURE_TOGGLE,
				'label'    => 'IndexNow',
				'severity' => Severity::INFO,
			),
			'enable_enhanced_slack_sharing'  => array(
				'action'   => Actions::SEO_FEATURE_TOGGLE,
				'label'    => 'Enhanced Slack sharing',
				'severity' => Severity::INFO,
			),
			// Crawl-optimization settings (Settings -> Crawl optimization) -
			// 28 keys, all sharing one action, each with its own label.
			'remove_shortlinks'              => array(
				'action'   => Actions::SEO_CRAWL_OPTIMIZATION_CHANGE,
				'label'    => 'Shortlink tag removal',
				'severity' => Severity::WARNING,
			),
			'remove_rest_api_links'          => array(
				'action'   => Actions::SEO_CRAWL_OPTIMIZATION_CHANGE,
				'label'    => 'REST API links removal',
				'severity' => Severity::WARNING,
			),
			'remove_rsd_wlw_links'           => array(
				'action'   => Actions::SEO_CRAWL_OPTIMIZATION_CHANGE,
				'label'    => 'RSD/WLW links removal',
				'severity' => Severity::WARNING,
			),
			'remove_oembed_links'            => array(
				'action'   => Actions::SEO_CRAWL_OPTIMIZATION_CHANGE,
				'label'    => 'oEmbed links removal',
				'severity' => Severity::WARNING,
			),
			'remove_generator'               => array(
				'action'   => Actions::SEO_CRAWL_OPTIMIZATION_CHANGE,
				'label'    => 'Generator tag removal',
				'severity' => Severity::WARNING,
			),
			'remove_pingback_header'         => array(
				'action'   => Actions::SEO_CRAWL_OPTIMIZATION_CHANGE,
				'label'    => 'Pingback header removal',
				'severity' => Severity::WARNING,
			),
			'remove_feed_global'             => array(
				'action'   => Actions::SEO_CRAWL_OPTIMIZATION_CHANGE,
				'label'    => 'Global feed removal',
				'severity' => Severity::WARNING,
			),
			'remove_feed_global_comments'    => array(
				'action'   => Actions::SEO_CRAWL_OPTIMIZATION_CHANGE,
				'label'    => 'Global comments feed removal',
				'severity' => Severity::WARNING,
			),
			'remove_feed_post_comments'      => array(
				'action'   => Actions::SEO_CRAWL_OPTIMIZATION_CHANGE,
				'label'    => 'Post comments feed removal',
				'severity' => Severity::WARNING,
			),
			'remove_feed_authors'            => array(
				'action'   => Actions::SEO_CRAWL_OPTIMIZATION_CHANGE,
				'label'    => 'Authors feed removal',
				'severity' => Severity::WARNING,
			),
			'remove_feed_post_types'         => array(
				'action'   => Actions::SEO_CRAWL_OPTIMIZATION_CHANGE,
				'label'    => 'Post-type feeds removal',
				'severity' => Severity::WARNING,
			),
			'remove_feed_categories'         => array(
				'action'   => Actions::SEO_CRAWL_OPTIMIZATION_CHANGE,
				'label'    => 'Category feeds removal',
				'severity' => Severity::WARNING,
			),
			'remove_feed_tags'               => array(
				'action'   => Actions::SEO_CRAWL_OPTIMIZATION_CHANGE,
				'label'    => 'Tag feeds removal',
				'severity' => Severity::WARNING,
			),
			'remove_feed_custom_taxonomies'  => array(
				'action'   => Actions::SEO_CRAWL_OPTIMIZATION_CHANGE,
				'label'    => 'Custom-taxonomy feeds removal',
				'severity' => Severity::WARNING,
			),
			'remove_feed_search'             => array(
				'action'   => Actions::SEO_CRAWL_OPTIMIZATION_CHANGE,
				'label'    => 'Search-results feed removal',
				'severity' => Severity::WARNING,
			),
			'remove_atom_rdf_feeds'          => array(
				'action'   => Actions::SEO_CRAWL_OPTIMIZATION_CHANGE,
				'label'    => 'Atom/RDF feeds removal',
				'severity' => Severity::WARNING,
			),
			'remove_emoji_scripts'           => array(
				'action'   => Actions::SEO_CRAWL_OPTIMIZATION_CHANGE,
				'label'    => 'Emoji scripts removal',
				'severity' => Severity::WARNING,
			),
			'deny_wp_json_crawling'          => array(
				'action'   => Actions::SEO_CRAWL_OPTIMIZATION_CHANGE,
				'label'    => 'wp-json crawling denial',
				'severity' => Severity::WARNING,
			),
			'deny_adsbot_crawling'           => array(
				'action'   => Actions::SEO_CRAWL_OPTIMIZATION_CHANGE,
				'label'    => 'AdsBot crawling denial',
				'severity' => Severity::WARNING,
			),
			'deny_google_extended_crawling'  => array(
				'action'   => Actions::SEO_CRAWL_OPTIMIZATION_CHANGE,
				'label'    => 'Google-Extended crawling denial',
				'severity' => Severity::WARNING,
			),
			'deny_gptbot_crawling'           => array(
				'action'   => Actions::SEO_CRAWL_OPTIMIZATION_CHANGE,
				'label'    => 'GPTBot crawling denial',
				'severity' => Severity::WARNING,
			),
			'deny_ccbot_crawling'            => array(
				'action'   => Actions::SEO_CRAWL_OPTIMIZATION_CHANGE,
				'label'    => 'CCBot crawling denial',
				'severity' => Severity::WARNING,
			),
			'search_cleanup'                 => array(
				'action'   => Actions::SEO_CRAWL_OPTIMIZATION_CHANGE,
				'label'    => 'Search-results cleanup',
				'severity' => Severity::WARNING,
			),
			'search_cleanup_emoji'           => array(
				'action'   => Actions::SEO_CRAWL_OPTIMIZATION_CHANGE,
				'label'    => 'Search-results emoji cleanup',
				'severity' => Severity::WARNING,
			),
			'search_cleanup_patterns'        => array(
				'action'   => Actions::SEO_CRAWL_OPTIMIZATION_CHANGE,
				'label'    => 'Search-results pattern cleanup',
				'severity' => Severity::WARNING,
			),
			'redirect_search_pretty_urls'    => array(
				'action'   => Actions::SEO_CRAWL_OPTIMIZATION_CHANGE,
				'label'    => 'Pretty search-URL redirect',
				'severity' => Severity::WARNING,
			),
			'deny_search_crawling'           => array(
				'action'   => Actions::SEO_CRAWL_OPTIMIZATION_CHANGE,
				'label'    => 'Search-results crawling denial',
				'severity' => Severity::WARNING,
			),
			'clean_campaign_tracking_urls'   => array(
				'action'   => Actions::SEO_CRAWL_OPTIMIZATION_CHANGE,
				'label'    => 'Campaign-tracking URL cleanup',
				'severity' => Severity::WARNING,
			),
			'clean_permalinks'               => array(
				'action'   => Actions::SEO_CRAWL_OPTIMIZATION_CHANGE,
				'label'    => 'Permalink cleanup',
				'severity' => Severity::WARNING,
			),
		),
		'wpseo_titles' => array(
			'separator'                      => array(
				'action'   => Actions::SEO_TITLE_SEPARATOR_CHANGE,
				'label'    => 'Title separator',
				'severity' => Severity::INFO,
			),
			'company_or_person'              => array(
				'action'   => Actions::SEO_KNOWLEDGE_GRAPH_TYPE_CHANGE,
				'label'    => 'Organization/Person type',
				'severity' => Severity::INFO,
			),
			'disable-attachment'             => array(
				'action'   => Actions::SEO_ATTACHMENT_REDIRECT_TOGGLE,
				'label'    => 'Attachment-URL redirect',
				'severity' => Severity::INFO,
			),
			'disable-author'                 => array(
				'action'   => Actions::SEO_ARCHIVE_TOGGLE,
				'label'    => 'Author archives',
				'severity' => Severity::WARNING,
			),
			'disable-date'                   => array(
				'action'   => Actions::SEO_ARCHIVE_TOGGLE,
				'label'    => 'Date archives',
				'severity' => Severity::WARNING,
			),
			'noindex-author-wpseo'           => array(
				'action'   => Actions::SEO_ARCHIVE_VISIBILITY_CHANGE,
				'label'    => 'Author archives search-results visibility',
				'severity' => Severity::WARNING,
			),
			'noindex-archive-wpseo'          => array(
				'action'   => Actions::SEO_ARCHIVE_VISIBILITY_CHANGE,
				'label'    => 'Date archives search-results visibility',
				'severity' => Severity::WARNING,
			),
			'title-author-wpseo'             => array(
				'action'   => Actions::SEO_ARCHIVE_TITLE_TEMPLATE_CHANGE,
				'label'    => 'Author archives title template',
				'severity' => Severity::INFO,
			),
			'title-archive-wpseo'            => array(
				'action'   => Actions::SEO_ARCHIVE_TITLE_TEMPLATE_CHANGE,
				'label'    => 'Date archives title template',
				'severity' => Severity::INFO,
			),
			'metadesc-author-wpseo'          => array(
				'action'   => Actions::SEO_ARCHIVE_METADESC_TEMPLATE_CHANGE,
				'label'    => 'Author archives meta description template',
				'severity' => Severity::INFO,
			),
			'metadesc-archive-wpseo'         => array(
				'action'   => Actions::SEO_ARCHIVE_METADESC_TEMPLATE_CHANGE,
				'label'    => 'Date archives meta description template',
				'severity' => Severity::INFO,
			),
			'schema-page-type-post'          => array(
				'action'   => Actions::SEO_DEFAULT_SCHEMA_PAGE_TYPE_CHANGE,
				'label'    => 'Default schema page type (Posts)',
				'severity' => Severity::WARNING,
			),
			'schema-page-type-page'          => array(
				'action'   => Actions::SEO_DEFAULT_SCHEMA_PAGE_TYPE_CHANGE,
				'label'    => 'Default schema page type (Pages)',
				'severity' => Severity::WARNING,
			),
			'schema-page-type-attachment'    => array(
				'action'   => Actions::SEO_DEFAULT_SCHEMA_PAGE_TYPE_CHANGE,
				'label'    => 'Default schema page type (Media)',
				'severity' => Severity::WARNING,
			),
			'schema-article-type-post'       => array(
				'action'   => Actions::SEO_DEFAULT_SCHEMA_ARTICLE_TYPE_CHANGE,
				'label'    => 'Default schema article type (Posts)',
				'severity' => Severity::WARNING,
			),
			'schema-article-type-page'       => array(
				'action'   => Actions::SEO_DEFAULT_SCHEMA_ARTICLE_TYPE_CHANGE,
				'label'    => 'Default schema article type (Pages)',
				'severity' => Severity::WARNING,
			),
			'schema-article-type-attachment' => array(
				'action'   => Actions::SEO_DEFAULT_SCHEMA_ARTICLE_TYPE_CHANGE,
				'label'    => 'Default schema article type (Media)',
				'severity' => Severity::WARNING,
			),
			'website_name'                   => array(
				'action'   => Actions::SEO_WEBSITE_NAME_CHANGE,
				'label'    => 'Website name',
				'severity' => Severity::INFO,
			),
			'alternate_website_name'         => array(
				'action'   => Actions::SEO_ALTERNATE_WEBSITE_NAME_CHANGE,
				'label'    => 'Alternate website name',
				'severity' => Severity::INFO,
			),
			'company_name'                   => array(
				'action'   => Actions::SEO_ORGANIZATION_NAME_CHANGE,
				'label'    => 'Organization name',
				'severity' => Severity::INFO,
			),
			'company_alternate_name'         => array(
				'action'   => Actions::SEO_ALTERNATE_ORGANIZATION_NAME_CHANGE,
				'label'    => 'Alternate organization name',
				'severity' => Severity::INFO,
			),
			'company_logo'                   => array(
				'action'   => Actions::SEO_ORGANIZATION_LOGO_CHANGE,
				'label'    => 'Organization logo',
				'severity' => Severity::INFO,
			),
			'company_or_person_user_id'      => array(
				'action'   => Actions::SEO_KNOWLEDGE_GRAPH_USER_CHANGE,
				'label'    => 'Organization/Person representative user',
				'severity' => Severity::INFO,
			),
			'person_logo'                    => array(
				'action'   => Actions::SEO_PERSONAL_LOGO_CHANGE,
				'label'    => 'Personal logo',
				'severity' => Severity::INFO,
			),
		),
		'wpseo_social' => array(
			'facebook_site'       => array(
				'action'   => Actions::SEO_SOCIAL_PROFILE_CHANGE,
				'label'    => 'Facebook profile URL',
				'severity' => Severity::INFO,
			),
			'instagram_url'       => array(
				'action'   => Actions::SEO_SOCIAL_PROFILE_CHANGE,
				'label'    => 'Instagram profile URL',
				'severity' => Severity::INFO,
			),
			'linkedin_url'        => array(
				'action'   => Actions::SEO_SOCIAL_PROFILE_CHANGE,
				'label'    => 'LinkedIn profile URL',
				'severity' => Severity::INFO,
			),
			'pinterest_url'       => array(
				'action'   => Actions::SEO_SOCIAL_PROFILE_CHANGE,
				'label'    => 'Pinterest profile URL',
				'severity' => Severity::INFO,
			),
			'twitter_site'        => array(
				'action'   => Actions::SEO_SOCIAL_PROFILE_CHANGE,
				'label'    => 'Twitter/X profile URL',
				'severity' => Severity::INFO,
			),
			'youtube_url'         => array(
				'action'   => Actions::SEO_SOCIAL_PROFILE_CHANGE,
				'label'    => 'YouTube profile URL',
				'severity' => Severity::INFO,
			),
			'wikipedia_url'       => array(
				'action'   => Actions::SEO_SOCIAL_PROFILE_CHANGE,
				'label'    => 'Wikipedia profile URL',
				'severity' => Severity::INFO,
			),
			'og_default_image'    => array(
				'action'   => Actions::SEO_SOCIAL_DEFAULT_IMAGE_CHANGE,
				'label'    => 'Default social-share image',
				'severity' => Severity::WARNING,
			),
			'og_default_image_id' => array(
				'action'   => Actions::SEO_SOCIAL_DEFAULT_IMAGE_CHANGE,
				'label'    => 'Default social-share image',
				'severity' => Severity::WARNING,
			),
			'twitter_card_type'   => array(
				'action'   => Actions::SEO_TWITTER_CARD_TYPE_CHANGE,
				'label'    => 'Default Twitter card type',
				'severity' => Severity::WARNING,
			),
			'pinterestverify'     => array(
				'action'   => Actions::SEO_PINTEREST_VERIFICATION_CHANGE,
				'label'    => 'Pinterest verification tag',
				'severity' => Severity::INFO,
			),
			'opengraph'           => array(
				'action'   => Actions::SEO_FEATURE_TOGGLE,
				'label'    => 'Open Graph metadata output',
				'severity' => Severity::INFO,
			),
			'twitter'             => array(
				'action'   => Actions::SEO_FEATURE_TOGGLE,
				'label'    => 'Twitter card metadata output',
				'severity' => Severity::INFO,
			),
		),
	);

	/**
	 * `wpseo_titles` setting-key *prefixes*, applied to every real,
	 * registered public post type (e.g. `noindex-post`, `noindex-page`,
	 * `noindex-attachment`, plus any custom public post type) - a
	 * deliberate generalization beyond WP Activity Log's own, narrower
	 * "only `page` is actually wired" behavior (see class docblock).
	 *
	 * @var array<string, array{action: string, label: string, severity: string}>
	 */
	private const DYNAMIC_POST_TYPE_KEYS = array(
		'noindex-'            => array(
			'action'   => Actions::SEO_POST_TYPE_VISIBILITY_CHANGE,
			'label'    => 'search-results visibility',
			'severity' => Severity::WARNING,
		),
		'title-'              => array(
			'action'   => Actions::SEO_POST_TYPE_TITLE_TEMPLATE_CHANGE,
			'label'    => 'title template',
			'severity' => Severity::INFO,
		),
		'metadesc-'           => array(
			'action'   => Actions::SEO_POST_TYPE_METADESC_TEMPLATE_CHANGE,
			'label'    => 'meta description template',
			'severity' => Severity::INFO,
		),
		'display-metabox-pt-' => array(
			'action'   => Actions::SEO_POST_TYPE_METABOX_TOGGLE,
			'label'    => 'SEO meta box visibility',
			'severity' => Severity::INFO,
		),
	);

	/**
	 * `wpseo_titles` setting-key prefixes applied to every real, registered
	 * public taxonomy (e.g. `noindex-tax-category`, `noindex-tax-post_tag`).
	 * `display-metabox-tax-post_format` is skipped, matching WP Activity
	 * Log's own exclusion for that one built-in taxonomy (it has no
	 * meaningful SEO meta box to toggle).
	 *
	 * @var array<string, array{action: string, label: string, severity: string}>
	 */
	private const DYNAMIC_TAXONOMY_KEYS = array(
		'noindex-tax-'         => array(
			'action'   => Actions::SEO_TAXONOMY_VISIBILITY_CHANGE,
			'label'    => 'search-results visibility',
			'severity' => Severity::WARNING,
		),
		'title-tax-'           => array(
			'action'   => Actions::SEO_TAXONOMY_TITLE_TEMPLATE_CHANGE,
			'label'    => 'title template',
			'severity' => Severity::INFO,
		),
		'metadesc-tax-'        => array(
			'action'   => Actions::SEO_TAXONOMY_METADESC_TEMPLATE_CHANGE,
			'label'    => 'meta description template',
			'severity' => Severity::INFO,
		),
		'display-metabox-tax-' => array(
			'action'   => Actions::SEO_TAXONOMY_METABOX_TOGGLE,
			'label'    => 'SEO meta box visibility',
			'severity' => Severity::INFO,
		),
	);

	/**
	 * Constructor.
	 */
	public function __construct() {

		parent::__construct();

		$this->register_hooks();
		$this->register_events();
	}

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	protected function register_hooks(): void {

		foreach ( array_keys( self::TRACKED_OPTIONS ) as $option ) {
			add_action( "update_option_{$option}", $this->guarded( array( $this, 'log_option_updated' ) ), 10, 3 );
		}
	}

	/**
	 * Declare the shared `yoast-seo` event group - every Yoast action
	 * (this logger's settings actions and `PostMetaActivityLogger`'s
	 * per-post actions) lives under this one group, so only this logger
	 * (listed first in `YoastSeoIntegration::get_logger_classes()`)
	 * registers it - a second registration from `PostMetaActivityLogger`
	 * would just overwrite this one's contribution, since filter callbacks
	 * for the same array key replace rather than merge.
	 *
	 * Uses the `pastmark_registered_events` filter (PM-151's settled
	 * convention, `source => 'yoast-seo'`) rather than editing
	 * `EventRegistry::get_events()` directly, per
	 * `docs/BUILDING-AN-INTEGRATION.md`.
	 *
	 * @return void
	 */
	protected function register_events(): void {

		add_filter( 'pastmark_registered_events', array( $this, 'add_event_group' ) );
	}

	/**
	 * `pastmark_registered_events` callback - adds Yoast SEO's event group.
	 *
	 * Built from one flat list (rather than 47 hand-duplicated entries)
	 * so every action's label/description/severity has exactly one place
	 * it's declared.
	 *
	 * @param array $events Registered events, keyed by event type.
	 * @return array
	 */
	public function add_event_group( array $events ): array {

		$events[ Events::YOAST_SEO ] = array(
			'label'   => __( 'Yoast SEO', 'pastmark' ),
			'source'  => 'yoast-seo',
			'actions' => $this->build_event_actions(),
		);

		return $events;
	}

	/**
	 * Build the full list of actions this integration's two loggers can
	 * emit, for `add_event_group()`.
	 *
	 * @return array
	 */
	protected function build_event_actions(): array {

		$descriptions = array(
			Actions::UPDATE                                => 'A Yoast SEO global setting changed that isn\'t covered by a more specific action below.',
			Actions::SEO_TITLE_SEPARATOR_CHANGE            => 'The title separator character changed.',
			Actions::SEO_KNOWLEDGE_GRAPH_TYPE_CHANGE       => 'The site represents an Organization vs. a Person setting changed.',
			Actions::SEO_POST_TYPE_VISIBILITY_CHANGE       => 'A post type\'s "show in search results" setting changed.',
			Actions::SEO_POST_TYPE_TITLE_TEMPLATE_CHANGE   => 'A post type\'s default SEO title template changed.',
			Actions::SEO_POST_TYPE_METADESC_TEMPLATE_CHANGE => 'A post type\'s default meta description template changed.',
			Actions::SEO_POST_TYPE_METABOX_TOGGLE          => 'A post type\'s "show SEO meta box" setting toggled.',
			Actions::SEO_AUTHOR_ADVANCED_META_TOGGLE       => 'Advanced/schema settings for authors toggled.',
			Actions::SEO_ATTACHMENT_REDIRECT_TOGGLE        => 'Attachment-URL redirect setting toggled.',
			Actions::SEO_USAGE_TRACKING_TOGGLE             => 'Usage-tracking setting toggled.',
			Actions::SEO_INTEGRATION_TOGGLE                => 'A third-party integration (Semrush/Zapier/Algolia/Wincher/Ryte) toggled.',
			Actions::SEO_SOCIAL_PROFILE_CHANGE             => 'A social-profile URL added, removed, or changed.',
			Actions::SEO_TAXONOMY_VISIBILITY_CHANGE        => 'A taxonomy\'s "show in search results" setting changed.',
			Actions::SEO_TAXONOMY_TITLE_TEMPLATE_CHANGE    => 'A taxonomy\'s default SEO title template changed.',
			Actions::SEO_TAXONOMY_METADESC_TEMPLATE_CHANGE => 'A taxonomy\'s default meta description template changed.',
			Actions::SEO_ARCHIVE_TOGGLE                    => 'Author or date archives enabled/disabled.',
			Actions::SEO_ARCHIVE_VISIBILITY_CHANGE         => 'Author or date archives\' "show in search results" setting changed.',
			Actions::SEO_ARCHIVE_TITLE_TEMPLATE_CHANGE     => 'Author or date archives\' default SEO title template changed.',
			Actions::SEO_ARCHIVE_METADESC_TEMPLATE_CHANGE  => 'Author or date archives\' default meta description template changed.',
			Actions::SEO_TAXONOMY_METABOX_TOGGLE           => 'A taxonomy\'s "show SEO meta box" setting toggled.',
			Actions::SEO_VERIFICATION_CODE_CHANGE          => 'A search-engine webmaster-tools verification code added or changed.',
			Actions::SEO_SOCIAL_DEFAULT_IMAGE_CHANGE       => 'The default social-share image changed.',
			Actions::SEO_TWITTER_CARD_TYPE_CHANGE          => 'The default Twitter card type changed.',
			Actions::SEO_PINTEREST_VERIFICATION_CHANGE     => 'The Pinterest verification tag changed.',
			Actions::SEO_DEFAULT_SCHEMA_PAGE_TYPE_CHANGE   => 'The site-wide default schema page type changed.',
			Actions::SEO_DEFAULT_SCHEMA_ARTICLE_TYPE_CHANGE => 'The site-wide default schema article type changed.',
			Actions::SEO_FEATURE_TOGGLE                    => 'An on/off SEO plugin feature toggled (SEO/readability analysis, cornerstone content, XML sitemaps, admin bar menu, REST head endpoint, IndexNow, Open Graph/Twitter output, ...).',
			Actions::SEO_CRAWL_OPTIMIZATION_CHANGE         => 'A crawl-optimization setting toggled.',
			Actions::SEO_WEBSITE_NAME_CHANGE               => 'The website name changed.',
			Actions::SEO_ALTERNATE_WEBSITE_NAME_CHANGE     => 'The alternate website name changed.',
			Actions::SEO_ORGANIZATION_NAME_CHANGE          => 'The organization name changed.',
			Actions::SEO_ALTERNATE_ORGANIZATION_NAME_CHANGE => 'The alternate organization name changed.',
			Actions::SEO_ORGANIZATION_LOGO_CHANGE          => 'The organization logo changed.',
			Actions::SEO_KNOWLEDGE_GRAPH_USER_CHANGE       => 'The user representing the site in the Knowledge Graph changed.',
			Actions::SEO_PERSONAL_LOGO_CHANGE              => 'The personal logo changed.',
			Actions::SEO_META_UPDATE                       => 'A post\'s Yoast SEO meta changed, not covered by a more specific action below.',
			Actions::SEO_TITLE_CHANGE                      => 'A post\'s SEO title changed.',
			Actions::SEO_METADESC_CHANGE                   => 'A post\'s meta description changed.',
			Actions::SEO_FOCUS_KEYWORD_CHANGE              => 'A post\'s focus keyword changed.',
			Actions::SEO_NOINDEX_CHANGE                    => 'A post\'s "allow search engines to show it in results" setting changed.',
			Actions::SEO_NOFOLLOW_CHANGE                   => 'A post\'s "should search engines follow its links" setting changed.',
			Actions::SEO_ADVANCED_ROBOTS_CHANGE            => 'A post\'s advanced meta robots setting changed.',
			Actions::SEO_CANONICAL_URL_CHANGE              => 'A post\'s canonical URL changed.',
			Actions::SEO_CORNERSTONE_CHANGE                => 'A post\'s cornerstone-content flag toggled.',
			Actions::SEO_BREADCRUMB_TITLE_CHANGE           => 'A post\'s breadcrumb title changed.',
			Actions::SEO_SCHEMA_PAGE_TYPE_CHANGE           => 'A post\'s schema page type changed.',
			Actions::SEO_SCHEMA_ARTICLE_TYPE_CHANGE        => 'A post\'s schema article type changed.',
		);

		$severities = array(
			Actions::UPDATE => Severity::WARNING,
		);

		foreach ( self::STATIC_TRACKED_KEYS as $option_map ) {
			foreach ( $option_map as $entry ) {
				$severities[ $entry['action'] ] = $entry['severity'];
			}
		}

		foreach ( array_merge( self::DYNAMIC_POST_TYPE_KEYS, self::DYNAMIC_TAXONOMY_KEYS ) as $entry ) {
			$severities[ $entry['action'] ] = $entry['severity'];
		}

		$severities[ Actions::SEO_META_UPDATE ]                = Severity::INFO;
		$severities[ Actions::SEO_TITLE_CHANGE ]               = Severity::INFO;
		$severities[ Actions::SEO_METADESC_CHANGE ]            = Severity::INFO;
		$severities[ Actions::SEO_FOCUS_KEYWORD_CHANGE ]       = Severity::INFO;
		$severities[ Actions::SEO_NOINDEX_CHANGE ]             = Severity::INFO;
		$severities[ Actions::SEO_NOFOLLOW_CHANGE ]            = Severity::INFO;
		$severities[ Actions::SEO_ADVANCED_ROBOTS_CHANGE ]     = Severity::INFO;
		$severities[ Actions::SEO_CANONICAL_URL_CHANGE ]       = Severity::INFO;
		$severities[ Actions::SEO_CORNERSTONE_CHANGE ]         = Severity::INFO;
		$severities[ Actions::SEO_BREADCRUMB_TITLE_CHANGE ]    = Severity::INFO;
		$severities[ Actions::SEO_SCHEMA_PAGE_TYPE_CHANGE ]    = Severity::INFO;
		$severities[ Actions::SEO_SCHEMA_ARTICLE_TYPE_CHANGE ] = Severity::INFO;

		$actions = array();

		foreach ( $descriptions as $action_key => $description ) {

			$severity = $severities[ $action_key ] ?? Severity::INFO;

			$actions[] = array(
				'key'            => $action_key,
				'label'          => Actions::resolve_label( $action_key ),
				'description'    => $description,
				'severity'       => $severity,
				'severity_label' => Severity::resolve_label( $severity ),
			);
		}

		return $actions;
	}

	/**
	 * Log a Yoast global-settings option update - one row per changed
	 * setting, per the class docblock.
	 *
	 * @param mixed  $old_value Old option value.
	 * @param mixed  $value     New option value.
	 * @param string $option    Option name.
	 * @return void
	 */
	public function log_option_updated( $old_value, $value, string $option ): void {

		if ( ! array_key_exists( $option, self::TRACKED_OPTIONS ) ) {
			return;
		}

		// Every one of Yoast's three options stores an array; normalize
		// defensively in case a filter/direct DB write ever leaves a
		// non-array value in place.
		$before = is_array( $old_value ) ? $old_value : array();
		$after  = is_array( $value ) ? $value : array();

		$handled_keys = array();

		foreach ( self::STATIC_TRACKED_KEYS[ $option ] ?? array() as $key => $entry ) {

			if ( ! array_key_exists( $key, $before ) && ! array_key_exists( $key, $after ) ) {
				continue;
			}

			$handled_keys[ $key ] = true;

			$this->log_key_change_if_different( $option, $key, $before, $after, $entry['action'], $entry['label'] );
		}

		if ( 'wpseo_titles' === $option ) {
			$handled_keys += $this->log_dynamic_post_type_keys( $before, $after );
			$handled_keys += $this->log_dynamic_taxonomy_keys( $before, $after );
		}

		$this->log_remaining_keys_as_fallback( $option, $before, $after, $handled_keys );
	}

	/**
	 * Compare one tracked key and log a dedicated row if it changed.
	 *
	 * @param string $option Option name (for context only).
	 * @param string $key    Array key within the option.
	 * @param array  $before Full option array before the save.
	 * @param array  $after  Full option array after the save.
	 * @param string $action `Actions` constant to log under.
	 * @param string $label  Human-readable label for the message/data.
	 * @return void
	 */
	protected function log_key_change_if_different( string $option, string $key, array $before, array $after, string $action, string $label ): void {

		$old = $before[ $key ] ?? null;
		$new = $after[ $key ] ?? null;

		if ( $old === $new ) {
			return;
		}

		$this->insert_event_log(
			Events::YOAST_SEO,
			$action,
			array(
				'object_type' => 'option',
				'object_id'   => 0,
				'user_id'     => get_current_user_id(),
				'message'     => sprintf( 'Yoast SEO "%s" setting changed.', $label ),
				'before_data' => wp_json_encode( array( $label => $old ) ),
				'after_data'  => wp_json_encode( array( $label => $new ) ),
				'context'     => array_merge(
					$this->get_common_context(),
					array(
						'option' => $option,
						'key'    => $key,
					)
				),
			)
		);
	}

	/**
	 * Check `DYNAMIC_POST_TYPE_KEYS`' prefixes against every real, public
	 * post type.
	 *
	 * @param array $before Full `wpseo_titles` array before the save.
	 * @param array $after  Full `wpseo_titles` array after the save.
	 * @return array<string, true> Keys handled, for the fallback pass.
	 */
	protected function log_dynamic_post_type_keys( array $before, array $after ): array {

		$handled    = array();
		$post_types = get_post_types( array( 'public' => true ), 'names' );

		foreach ( $post_types as $post_type ) {
			foreach ( self::DYNAMIC_POST_TYPE_KEYS as $prefix => $entry ) {

				$key = $prefix . $post_type;

				if ( ! array_key_exists( $key, $before ) && ! array_key_exists( $key, $after ) ) {
					continue;
				}

				$handled[ $key ] = true;

				$label = sprintf( '%s (%s)', $entry['label'], $post_type );

				$this->log_key_change_if_different( 'wpseo_titles', $key, $before, $after, $entry['action'], $label );
			}
		}

		return $handled;
	}

	/**
	 * Check `DYNAMIC_TAXONOMY_KEYS`' prefixes against every real, public
	 * taxonomy - `display-metabox-tax-post_format` is skipped, matching WP
	 * Activity Log's own exclusion for that one built-in taxonomy.
	 *
	 * @param array $before Full `wpseo_titles` array before the save.
	 * @param array $after  Full `wpseo_titles` array after the save.
	 * @return array<string, true> Keys handled, for the fallback pass.
	 */
	protected function log_dynamic_taxonomy_keys( array $before, array $after ): array {

		$handled    = array();
		$taxonomies = get_taxonomies( array( 'public' => true ), 'names' );

		foreach ( $taxonomies as $taxonomy ) {
			foreach ( self::DYNAMIC_TAXONOMY_KEYS as $prefix => $entry ) {

				$key = $prefix . $taxonomy;

				if ( 'display-metabox-tax-post_format' === $key ) {
					continue;
				}

				if ( ! array_key_exists( $key, $before ) && ! array_key_exists( $key, $after ) ) {
					continue;
				}

				$handled[ $key ] = true;

				$label = sprintf( '%s (%s)', $entry['label'], $taxonomy );

				$this->log_key_change_if_different( 'wpseo_titles', $key, $before, $after, $entry['action'], $label );
			}
		}

		return $handled;
	}

	/**
	 * Aggregate every changed key not already handled above into one
	 * fallback `Actions::UPDATE` row, diffed via `ContentDiffer` on the
	 * pretty-printed array - the ticket's original behavior, kept as a
	 * safety net for settings this logger doesn't yet name explicitly.
	 *
	 * @param string $option       Option name.
	 * @param array  $before       Full option array before the save.
	 * @param array  $after        Full option array after the save.
	 * @param array  $handled_keys Keys already logged individually above.
	 * @return void
	 */
	protected function log_remaining_keys_as_fallback( string $option, array $before, array $after, array $handled_keys ): void {

		$remaining_before = array_diff_key( $before, $handled_keys );
		$remaining_after  = array_diff_key( $after, $handled_keys );

		if ( $remaining_before === $remaining_after ) {
			return;
		}

		$label = self::TRACKED_OPTIONS[ $option ];

		$before_json = $this->encode_option_data( $remaining_before );
		$after_json  = $this->encode_option_data( $remaining_after );

		$diff = ContentDiffer::diff( $before_json, $after_json );

		$log_data = array(
			'object_type' => 'option',
			'object_id'   => 0,
			'user_id'     => get_current_user_id(),
			'severity'    => Severity::WARNING,
			'message'     => sprintf( 'Yoast SEO "%s" settings updated.', $label ),
			'context'     => array_merge( $this->get_common_context(), array( 'option' => $option ) ),
		);

		if ( null !== $diff ) {
			$log_data['after_data'] = wp_json_encode( array( $label . ' diff' => $diff ) );
		} else {
			$log_data['before_data'] = $before_json;
			$log_data['after_data']  = $after_json;
		}

		$this->insert_event_log( Events::YOAST_SEO, Actions::UPDATE, $log_data );
	}

	/**
	 * Encode an option's array for diffing/storage.
	 *
	 * `ContentDiffer` diffs line-by-line; the default `wp_json_encode()`
	 * single-line output would make any change look like "the whole line
	 * changed" and defeat diffing entirely - the same fix PM-154 needed for
	 * ACF field-group data, applied here for the identical reason.
	 *
	 * @param array $data Option data.
	 * @return string
	 */
	protected function encode_option_data( array $data ): string {

		return (string) wp_json_encode( $data, JSON_PRETTY_PRINT );
	}
}
