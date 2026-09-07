<?php // phpcs:ignore WordPress.Files.FileName.NotHyphenatedLowercase

namespace Pastmark\Constants;

defined( 'ABSPATH' ) || exit;

/**
 * Action constants.
 */
class Actions {

	/**
	 * Create action.
	 */
	public const CREATE = 'create';

	/**
	 * Update action.
	 */
	public const UPDATE = 'update';

	/**
	 * Delete action.
	 */
	public const DELETE = 'delete';

	/**
	 * Restore action.
	 */
	public const RESTORE = 'restore';

	/**
	 * Login action.
	 */
	public const LOGIN = 'login';

	/**
	 * Logout action.
	 */
	public const LOGOUT = 'logout';

	/**
	 * Failed login action.
	 */
	public const FAILED_LOGIN = 'failed_login';

	/**
	 * Switch user action.
	 */
	public const SWITCH_USER = 'switch_user';

	/**
	 * Register action.
	 */
	public const REGISTER = 'register';

	/**
	 * Role change action.
	 */
	public const ROLE_CHANGE = 'role_change';

	/**
	 * Status change action.
	 */
	public const STATUS_CHANGE = 'status_change';

	/**
	 * Activate action.
	 */
	public const ACTIVATE = 'activate';

	/**
	 * Deactivate action.
	 */
	public const DEACTIVATE = 'deactivate';

	/**
	 * Switch action.
	 */
	public const SWITCH = 'switch';

	/**
	 * Item update action.
	 */
	public const ITEM_UPDATE = 'item_update';

	/**
	 * Update check action.
	 */
	public const UPDATE_CHECK = 'update_check';

	/**
	 * Author/ownership change action.
	 */
	public const AUTHOR_CHANGE = 'author_change';

	/**
	 * Slug/URL change action.
	 */
	public const SLUG_CHANGE = 'slug_change';

	/**
	 * Visibility change action.
	 */
	public const VISIBILITY_CHANGE = 'visibility_change';

	/**
	 * Date change action.
	 */
	public const DATE_CHANGE = 'date_change';

	/**
	 * Sticky change action.
	 */
	public const STICKY_CHANGE = 'sticky_change';

	/**
	 * Parent change action.
	 */
	public const PARENT_CHANGE = 'parent_change';

	/**
	 * Template change action.
	 */
	public const TEMPLATE_CHANGE = 'template_change';

	/**
	 * Content change action.
	 */
	public const CONTENT_CHANGE = 'content_change';

	/**
	 * Reply action.
	 */
	public const REPLY = 'reply';

	/**
	 * Spam status change action.
	 */
	public const SPAM_CHANGE = 'spam_change';

	/**
	 * Password change action.
	 */
	public const PASSWORD_CHANGE = 'password_change';

	/**
	 * Email change action.
	 */
	public const EMAIL_CHANGE = 'email_change';

	/**
	 * Password reset sent action.
	 */
	public const PASSWORD_RESET_SENT = 'password_reset_sent';

	/**
	 * Super Admin change action.
	 */
	public const SUPER_ADMIN_CHANGE = 'super_admin_change';

	/**
	 * Added to site action.
	 */
	public const ADD_TO_SITE = 'add_to_site';

	/**
	 * Removed from site action.
	 */
	public const REMOVE_FROM_SITE = 'remove_from_site';

	/**
	 * User meta field added action.
	 */
	public const USER_META_ADD = 'user_meta_add';

	/**
	 * User meta field updated action.
	 */
	public const USER_META_UPDATE = 'user_meta_update';

	/**
	 * User meta field deleted action.
	 */
	public const USER_META_DELETE = 'user_meta_delete';

	/**
	 * Application password created action.
	 */
	public const APP_PASSWORD_CREATE = 'app_password_create';

	/**
	 * Application password revoked action.
	 */
	public const APP_PASSWORD_REVOKE = 'app_password_revoke';

	/**
	 * Featured image change action.
	 */
	public const FEATURED_IMAGE_CHANGE = 'featured_image_change';

	/**
	 * Site icon change action.
	 */
	public const SITE_ICON_CHANGE = 'site_icon_change';

	/**
	 * Install action.
	 */
	public const INSTALL = 'install';

	/**
	 * Failed install action.
	 */
	public const INSTALL_FAILED = 'install_failed';

	/**
	 * Automatic update setting change action.
	 */
	public const AUTO_UPDATE_CHANGE = 'auto_update_change';

	/**
	 * File edit action.
	 */
	public const FILE_EDIT = 'file_edit';

	/**
	 * WordPress core update action.
	 */
	public const CORE_UPDATE = 'core_update';

	/**
	 * Product created (draft) action.
	 */
	public const PRODUCT_CREATE = 'product_create';

	/**
	 * Product published action.
	 */
	public const PRODUCT_PUBLISH = 'product_publish';

	/**
	 * Product moved to trash action.
	 */
	public const PRODUCT_TRASH = 'product_trash';

	/**
	 * Product permanently deleted action.
	 */
	public const PRODUCT_DELETE = 'product_delete';

	/**
	 * Product restored from trash action.
	 */
	public const PRODUCT_RESTORE = 'product_restore';

	/**
	 * Product status change action.
	 */
	public const PRODUCT_STATUS_CHANGE = 'product_status_change';

	/**
	 * Product renamed action.
	 */
	public const PRODUCT_RENAME = 'product_rename';

	/**
	 * Product category change action.
	 */
	public const PRODUCT_CATEGORY_CHANGE = 'product_category_change';

	/**
	 * Product catalog visibility change action.
	 */
	public const PRODUCT_VISIBILITY_CHANGE = 'product_visibility_change';

	/**
	 * Product SKU change action.
	 */
	public const PRODUCT_SKU_CHANGE = 'product_sku_change';

	/**
	 * Product price change action.
	 */
	public const PRODUCT_PRICE_CHANGE = 'product_price_change';

	/**
	 * Product stock status change action.
	 */
	public const PRODUCT_STOCK_STATUS_CHANGE = 'product_stock_status_change';

	/**
	 * Product stock quantity change action (manual, via dashboard).
	 */
	public const PRODUCT_STOCK_QTY_CHANGE = 'product_stock_qty_change';

	/**
	 * Product stock quantity change action (automated, e.g. order or plugin driven).
	 */
	public const PRODUCT_STOCK_AUTO_CHANGE = 'product_stock_auto_change';

	/**
	 * Product category created action.
	 */
	public const PRODUCT_CATEGORY_CREATE = 'product_category_create';

	/**
	 * Product category deleted action.
	 */
	public const PRODUCT_CATEGORY_DELETE = 'product_category_delete';

	/**
	 * Order placed action.
	 */
	public const ORDER_PLACED = 'order_placed';

	/**
	 * Order status change action.
	 */
	public const ORDER_STATUS_CHANGE = 'order_status_change';

	/**
	 * Order moved to trash action.
	 */
	public const ORDER_TRASH = 'order_trash';

	/**
	 * Order restored from trash action.
	 */
	public const ORDER_RESTORE = 'order_restore';

	/**
	 * Order permanently deleted action.
	 */
	public const ORDER_DELETE = 'order_delete';

	/**
	 * Order edited action.
	 */
	public const ORDER_EDIT = 'order_edit';

	/**
	 * Order refunded action.
	 */
	public const ORDER_REFUND = 'order_refund';

	/**
	 * Order note added action.
	 */
	public const ORDER_NOTE_ADD = 'order_note_add';

	/**
	 * Order note deleted action.
	 */
	public const ORDER_NOTE_DELETE = 'order_note_delete';

	/**
	 * Coupon created action.
	 */
	public const COUPON_CREATE = 'coupon_create';

	/**
	 * Coupon amount change action.
	 */
	public const COUPON_AMOUNT_CHANGE = 'coupon_amount_change';

	/**
	 * Coupon status change action.
	 */
	public const COUPON_STATUS_CHANGE = 'coupon_status_change';

	/**
	 * Coupon renamed action.
	 */
	public const COUPON_RENAME = 'coupon_rename';

	/**
	 * Coupon moved to trash action.
	 */
	public const COUPON_TRASH = 'coupon_trash';

	/**
	 * Coupon restored from trash action.
	 */
	public const COUPON_RESTORE = 'coupon_restore';

	/**
	 * Coupon permanently deleted action.
	 */
	public const COUPON_DELETE = 'coupon_delete';

	/**
	 * Product review created action.
	 */
	public const REVIEW_CREATE = 'review_create';

	/**
	 * Product review approved action.
	 */
	public const REVIEW_APPROVE = 'review_approve';

	/**
	 * Product review unapproved action.
	 */
	public const REVIEW_UNAPPROVE = 'review_unapprove';

	/**
	 * Product review marked as spam action.
	 */
	public const REVIEW_SPAM = 'review_spam';

	/**
	 * Product review moved to trash action.
	 */
	public const REVIEW_TRASH = 'review_trash';

	/**
	 * Product review permanently deleted action.
	 */
	public const REVIEW_DELETE = 'review_delete';

	/**
	 * ACF field group created action.
	 */
	public const FIELD_GROUP_CREATE = 'field_group_create';

	/**
	 * ACF field group settings/location edited action.
	 */
	public const FIELD_GROUP_UPDATE = 'field_group_update';

	/**
	 * ACF field group permanently deleted action.
	 */
	public const FIELD_GROUP_DELETE = 'field_group_delete';

	/**
	 * ACF field group moved to trash action.
	 */
	public const FIELD_GROUP_TRASH = 'field_group_trash';

	/**
	 * ACF field group restored from trash action.
	 */
	public const FIELD_GROUP_RESTORE = 'field_group_restore';

	/**
	 * ACF field group duplicated action.
	 */
	public const FIELD_GROUP_DUPLICATE = 'field_group_duplicate';

	/**
	 * ACF field added to a group action.
	 */
	public const FIELD_CREATE = 'field_create';

	/**
	 * ACF field settings edited action.
	 */
	public const FIELD_UPDATE = 'field_update';

	/**
	 * ACF field removed from a group action.
	 */
	public const FIELD_DELETE = 'field_delete';

	/**
	 * Form (WPForms/Gravity Forms) created action.
	 */
	public const FORM_CREATE = 'form_create';

	/**
	 * Form fields/settings/notifications/confirmations edited action.
	 */
	public const FORM_UPDATE = 'form_update';

	/**
	 * Form permanently deleted action.
	 */
	public const FORM_DELETE = 'form_delete';

	/**
	 * Form moved to trash action.
	 */
	public const FORM_TRASH = 'form_trash';

	/**
	 * Form restored from trash action.
	 */
	public const FORM_RESTORE = 'form_restore';

	/**
	 * Form duplicated action.
	 */
	public const FORM_DUPLICATE = 'form_duplicate';

	/**
	 * A visitor submitted a form (WPForms/Gravity Forms) action.
	 */
	public const ENTRY_CREATE = 'entry_create';

	/**
	 * A form's notification email failed to send action.
	 */
	public const NOTIFICATION_FAILED = 'notification_failed';

	// --- Gravity Forms granular parity actions (PM-169, added on real user
	// request after directly comparing against WP Activity Log's own
	// Gravity Forms sensor - `wp-security-audit-log`'s alerts 5700-5720 -
	// same "compare against WP Activity Log, add the gap" pattern the
	// Sprint 10 SEO granular actions above already followed. Sprint 8's
	// original 8 `FORM_*`/`ENTRY_CREATE`/`NOTIFICATION_FAILED` actions above
	// were a deliberate MVP scope (see `docs/sprints/sprint-08.md`, PM-156),
	// not a final one - these fill the remaining gap: form
	// activation/import-export/global settings, confirmations, notifications
	// (the create/update/delete/activate/deactivate kind - distinct from
	// `NOTIFICATION_FAILED`'s send-failure concept above), and entry
	// moderation (star/read/trash/delete/notes/admin edit/export). ---

	/**
	 * Form activated action.
	 */
	public const FORM_ACTIVATE = 'form_activate';

	/**
	 * Form deactivated action.
	 */
	public const FORM_DEACTIVATE = 'form_deactivate';

	/**
	 * Form imported action.
	 */
	public const FORM_IMPORT = 'form_import';

	/**
	 * Form exported action.
	 */
	public const FORM_EXPORT = 'form_export';

	/**
	 * Gravity Forms global plugin setting changed action (distinct from
	 * `FORM_UPDATE`, which is a single form's own fields/settings).
	 */
	public const FORM_SETTINGS_CHANGE = 'form_settings_change';

	/**
	 * Form confirmation created action.
	 */
	public const CONFIRMATION_CREATE = 'confirmation_create';

	/**
	 * Form confirmation edited action.
	 */
	public const CONFIRMATION_UPDATE = 'confirmation_update';

	/**
	 * Form confirmation deleted action.
	 */
	public const CONFIRMATION_DELETE = 'confirmation_delete';

	/**
	 * Form confirmation activated action.
	 */
	public const CONFIRMATION_ACTIVATE = 'confirmation_activate';

	/**
	 * Form confirmation deactivated action.
	 */
	public const CONFIRMATION_DEACTIVATE = 'confirmation_deactivate';

	/**
	 * Form notification created action.
	 */
	public const NOTIFICATION_CREATE = 'notification_create';

	/**
	 * Form notification edited action.
	 */
	public const NOTIFICATION_UPDATE = 'notification_update';

	/**
	 * Form notification deleted action.
	 */
	public const NOTIFICATION_DELETE = 'notification_delete';

	/**
	 * Form notification activated action.
	 */
	public const NOTIFICATION_ACTIVATE = 'notification_activate';

	/**
	 * Form notification deactivated action.
	 */
	public const NOTIFICATION_DEACTIVATE = 'notification_deactivate';

	/**
	 * Entry starred action.
	 */
	public const ENTRY_STAR = 'entry_star';

	/**
	 * Entry unstarred action.
	 */
	public const ENTRY_UNSTAR = 'entry_unstar';

	/**
	 * Entry marked read action.
	 */
	public const ENTRY_READ = 'entry_read';

	/**
	 * Entry marked unread action.
	 */
	public const ENTRY_UNREAD = 'entry_unread';

	/**
	 * Entry moved to trash action.
	 */
	public const ENTRY_TRASH = 'entry_trash';

	/**
	 * Entry restored from trash action.
	 */
	public const ENTRY_RESTORE = 'entry_restore';

	/**
	 * Entry permanently deleted action.
	 */
	public const ENTRY_DELETE = 'entry_delete';

	/**
	 * Entries exported action.
	 */
	public const ENTRY_EXPORT = 'entry_export';

	/**
	 * Entry note added action.
	 */
	public const ENTRY_NOTE_ADD = 'entry_note_add';

	/**
	 * Entry note deleted action.
	 */
	public const ENTRY_NOTE_DELETE = 'entry_note_delete';

	/**
	 * Entry field values edited by an admin action.
	 */
	public const ENTRY_EDIT = 'entry_edit';

	// --- WPForms granular parity actions (PM-170, same "compare against WP
	// Activity Log, add the gap" pass as PM-169's Gravity Forms work above -
	// see that block's docblock. Most of WPForms' own gap reuses the
	// `FORM_RENAME` constant below plus the `CONFIRMATION_*`/`NOTIFICATION_*`
	// constants already defined above for Gravity Forms - WPForms' own
	// confirmations/notifications are conceptually identical sub-resources,
	// just extracted from the one combined save snapshot
	// `WPForms\FormActivityLogger` already captures rather than from
	// per-resource native hooks (WPForms has none, unlike Gravity Forms). ---

	/**
	 * Form renamed action (title changed with no other content change).
	 */
	public const FORM_RENAME = 'form_rename';

	/**
	 * A forms plugin's third-party service/provider integration (e.g. an
	 * email marketing connection) added or removed action.
	 */
	public const SERVICE_INTEGRATION_CHANGE = 'service_integration_change';

	/**
	 * A forms plugin's own addon installed/activated action.
	 */
	public const ADDON_ACTIVATE = 'addon_activate';

	/**
	 * A forms plugin's own addon deactivated action.
	 */
	public const ADDON_DEACTIVATE = 'addon_deactivate';

	/**
	 * A post's SEO plugin (Yoast SEO/RankMath) meta box changed action -
	 * generic fallback for any tracked SEO meta key not covered by one of
	 * the dedicated `SEO_*_CHANGE` actions below (kept so an unmapped key -
	 * e.g. a future Yoast release, or a premium-only field - still logs
	 * something instead of being silently dropped).
	 */
	public const SEO_META_UPDATE = 'seo_meta_update';

	// --- Yoast SEO/RankMath granular per-post meta actions (PM-166/167,
	// Sprint 10 - added on real user request after comparing against WP
	// Activity Log's own per-field Yoast alert set, see sprint-10.md). ---

	/**
	 * SEO title (meta title) changed for a post.
	 */
	public const SEO_TITLE_CHANGE = 'seo_title_change';

	/**
	 * Meta description changed for a post.
	 */
	public const SEO_METADESC_CHANGE = 'seo_metadesc_change';

	/**
	 * Focus keyword/keyphrase changed for a post.
	 */
	public const SEO_FOCUS_KEYWORD_CHANGE = 'seo_focus_keyword_change';

	/**
	 * "Allow search engines to show this post in search results"
	 * (meta robots noindex) toggled for a post.
	 */
	public const SEO_NOINDEX_CHANGE = 'seo_noindex_change';

	/**
	 * "Should search engines follow links on this post" (meta robots
	 * nofollow) toggled for a post.
	 */
	public const SEO_NOFOLLOW_CHANGE = 'seo_nofollow_change';

	/**
	 * Advanced meta robots setting (noarchive/noimageindex/nosnippet/...)
	 * changed for a post.
	 */
	public const SEO_ADVANCED_ROBOTS_CHANGE = 'seo_advanced_robots_change';

	/**
	 * Canonical URL changed for a post.
	 */
	public const SEO_CANONICAL_URL_CHANGE = 'seo_canonical_url_change';

	/**
	 * Cornerstone-content flag toggled for a post.
	 */
	public const SEO_CORNERSTONE_CHANGE = 'seo_cornerstone_change';

	/**
	 * Breadcrumb title changed for a post.
	 */
	public const SEO_BREADCRUMB_TITLE_CHANGE = 'seo_breadcrumb_title_change';

	/**
	 * Schema "Page type" changed for a specific post.
	 */
	public const SEO_SCHEMA_PAGE_TYPE_CHANGE = 'seo_schema_page_type_change';

	/**
	 * Schema "Article type" changed for a specific post.
	 */
	public const SEO_SCHEMA_ARTICLE_TYPE_CHANGE = 'seo_schema_article_type_change';

	// --- Yoast SEO/RankMath granular global-settings actions. ---

	/**
	 * Title separator changed.
	 */
	public const SEO_TITLE_SEPARATOR_CHANGE = 'seo_title_separator_change';

	/**
	 * "Organization or Person" (Knowledge Graph) type changed.
	 */
	public const SEO_KNOWLEDGE_GRAPH_TYPE_CHANGE = 'seo_knowledge_graph_type_change';

	/**
	 * A post type's "show in search results" setting changed.
	 */
	public const SEO_POST_TYPE_VISIBILITY_CHANGE = 'seo_post_type_visibility_change';

	/**
	 * A post type's default SEO title template changed.
	 */
	public const SEO_POST_TYPE_TITLE_TEMPLATE_CHANGE = 'seo_post_type_title_template_change';

	/**
	 * A post type's default meta description template changed.
	 */
	public const SEO_POST_TYPE_METADESC_TEMPLATE_CHANGE = 'seo_post_type_metadesc_template_change';

	/**
	 * A post type's "show SEO meta box" setting toggled.
	 */
	public const SEO_POST_TYPE_METABOX_TOGGLE = 'seo_post_type_metabox_toggle';

	/**
	 * "Advanced"/schema settings for authors toggled.
	 */
	public const SEO_AUTHOR_ADVANCED_META_TOGGLE = 'seo_author_advanced_meta_toggle';

	/**
	 * Attachment-URL redirect setting toggled.
	 */
	public const SEO_ATTACHMENT_REDIRECT_TOGGLE = 'seo_attachment_redirect_toggle';

	/**
	 * Usage-tracking setting toggled.
	 */
	public const SEO_USAGE_TRACKING_TOGGLE = 'seo_usage_tracking_toggle';

	/**
	 * A third-party integration (Semrush/Zapier/Algolia/Wincher/Ryte)
	 * toggled.
	 */
	public const SEO_INTEGRATION_TOGGLE = 'seo_integration_toggle';

	/**
	 * A social-profile URL (Facebook/Instagram/LinkedIn/Twitter/YouTube/
	 * Pinterest/Wikipedia) added, removed, or changed.
	 */
	public const SEO_SOCIAL_PROFILE_CHANGE = 'seo_social_profile_change';

	/**
	 * A taxonomy's "show in search results" setting changed.
	 */
	public const SEO_TAXONOMY_VISIBILITY_CHANGE = 'seo_taxonomy_visibility_change';

	/**
	 * A taxonomy's default SEO title template changed.
	 */
	public const SEO_TAXONOMY_TITLE_TEMPLATE_CHANGE = 'seo_taxonomy_title_template_change';

	/**
	 * A taxonomy's default meta description template changed.
	 */
	public const SEO_TAXONOMY_METADESC_TEMPLATE_CHANGE = 'seo_taxonomy_metadesc_template_change';

	/**
	 * Author or date archives enabled/disabled.
	 */
	public const SEO_ARCHIVE_TOGGLE = 'seo_archive_toggle';

	/**
	 * Author or date archives' "show in search results" setting changed.
	 */
	public const SEO_ARCHIVE_VISIBILITY_CHANGE = 'seo_archive_visibility_change';

	/**
	 * Author or date archives' default SEO title template changed.
	 */
	public const SEO_ARCHIVE_TITLE_TEMPLATE_CHANGE = 'seo_archive_title_template_change';

	/**
	 * Author or date archives' default meta description template changed.
	 */
	public const SEO_ARCHIVE_METADESC_TEMPLATE_CHANGE = 'seo_archive_metadesc_template_change';

	/**
	 * A taxonomy's "show SEO meta box" setting toggled.
	 */
	public const SEO_TAXONOMY_METABOX_TOGGLE = 'seo_taxonomy_metabox_toggle';

	/**
	 * A search-engine webmaster-tools verification code added/changed.
	 */
	public const SEO_VERIFICATION_CODE_CHANGE = 'seo_verification_code_change';

	/**
	 * Default social-share image changed.
	 */
	public const SEO_SOCIAL_DEFAULT_IMAGE_CHANGE = 'seo_social_default_image_change';

	/**
	 * Default Twitter card type changed.
	 */
	public const SEO_TWITTER_CARD_TYPE_CHANGE = 'seo_twitter_card_type_change';

	/**
	 * Pinterest verification tag changed.
	 */
	public const SEO_PINTEREST_VERIFICATION_CHANGE = 'seo_pinterest_verification_change';

	/**
	 * Default schema "Page type" changed (site-wide default, not a single
	 * post) - distinct from `SEO_SCHEMA_PAGE_TYPE_CHANGE`, which is per-post.
	 */
	public const SEO_DEFAULT_SCHEMA_PAGE_TYPE_CHANGE = 'seo_default_schema_page_type_change';

	/**
	 * Default schema "Article type" changed (site-wide default) - distinct
	 * from `SEO_SCHEMA_ARTICLE_TYPE_CHANGE`, which is per-post.
	 */
	public const SEO_DEFAULT_SCHEMA_ARTICLE_TYPE_CHANGE = 'seo_default_schema_article_type_change';

	/**
	 * A plain on/off SEO plugin feature toggled (SEO analysis, readability
	 * analysis, cornerstone content feature, text link counter, XML
	 * sitemaps, admin bar menu, REST API head endpoint, IndexNow, enhanced
	 * Slack sharing, Open Graph output, Twitter card output, ...) -
	 * deliberately one shared action for every such toggle (mirroring WP
	 * Activity Log's own real, current behavior - it consolidated its own
	 * former per-feature alert IDs into exactly this single generic one).
	 */
	public const SEO_FEATURE_TOGGLE = 'seo_feature_toggle';

	/**
	 * A crawl-optimization setting (shortlinks, RSD/WLW links, feeds,
	 * emoji scripts, AI-bot crawling, search-results cleanup, ...) toggled.
	 */
	public const SEO_CRAWL_OPTIMIZATION_CHANGE = 'seo_crawl_optimization_change';

	/**
	 * Website name changed.
	 */
	public const SEO_WEBSITE_NAME_CHANGE = 'seo_website_name_change';

	/**
	 * Alternate website name changed.
	 */
	public const SEO_ALTERNATE_WEBSITE_NAME_CHANGE = 'seo_alternate_website_name_change';

	/**
	 * Organization name changed.
	 */
	public const SEO_ORGANIZATION_NAME_CHANGE = 'seo_organization_name_change';

	/**
	 * Alternate organization name changed.
	 */
	public const SEO_ALTERNATE_ORGANIZATION_NAME_CHANGE = 'seo_alternate_organization_name_change';

	/**
	 * Organization logo changed.
	 */
	public const SEO_ORGANIZATION_LOGO_CHANGE = 'seo_organization_logo_change';

	/**
	 * The user representing the site in the Knowledge Graph changed.
	 */
	public const SEO_KNOWLEDGE_GRAPH_USER_CHANGE = 'seo_knowledge_graph_user_change';

	/**
	 * Personal logo changed.
	 */
	public const SEO_PERSONAL_LOGO_CHANGE = 'seo_personal_logo_change';

	// --- RankMath-specific granular actions (PM-167, Sprint 10). Conceptually
	// identical fields already covered by a Yoast-named `SEO_*_CHANGE`
	// constant above are deliberately reused (event_type + integration
	// already distinguish "Yoast changed the title" from "RankMath changed
	// the title" - no need for a second, duplicate constant per plugin).
	// These four are genuinely RankMath-specific: it splits its "advanced
	// robots" meta into three separately-toggleable settings where Yoast
	// bundles them into one freeform field, and it has no equivalent to
	// Yoast's title/metadesc/social template split at all for its module
	// toggle. ---

	/**
	 * "No Archive" robots-meta toggle (RankMath splits this out from its
	 * combined noindex/nofollow meta; Yoast bundles it into one advanced-
	 * robots field instead - see `SEO_ADVANCED_ROBOTS_CHANGE`).
	 */
	public const SEO_ROBOTS_NOARCHIVE_CHANGE = 'seo_robots_noarchive_change';

	/**
	 * "No Image Index" robots-meta toggle.
	 */
	public const SEO_ROBOTS_NOIMAGEINDEX_CHANGE = 'seo_robots_noimageindex_change';

	/**
	 * "No Snippet" robots-meta toggle.
	 */
	public const SEO_ROBOTS_NOSNIPPET_CHANGE = 'seo_robots_nosnippet_change';

	/**
	 * Max-snippet-length advanced-robots setting changed.
	 */
	public const SEO_MAX_SNIPPET_LENGTH_CHANGE = 'seo_max_snippet_length_change';

	/**
	 * Max-video-preview advanced-robots setting changed.
	 */
	public const SEO_MAX_VIDEO_PREVIEW_CHANGE = 'seo_max_video_preview_change';

	/**
	 * Max-image-preview advanced-robots setting changed.
	 */
	public const SEO_MAX_IMAGE_PREVIEW_CHANGE = 'seo_max_image_preview_change';

	/**
	 * An SEO plugin's own feature module enabled/disabled (RankMath's
	 * `rank_math/module_changed` action - Sitemap, Schema, Redirections,
	 * etc. can each be toggled independently).
	 */
	public const SEO_MODULE_TOGGLE = 'seo_module_toggle';

	// --- Redirection plugin actions (PM-168, Sprint 10). ---

	/**
	 * A single redirect rule was created.
	 */
	public const REDIRECT_CREATE = 'redirect_create';

	/**
	 * A single redirect rule's fields were edited.
	 */
	public const REDIRECT_UPDATE = 'redirect_update';

	/**
	 * A single redirect rule was permanently deleted.
	 */
	public const REDIRECT_DELETE = 'redirect_delete';

	/**
	 * A single redirect rule was enabled.
	 */
	public const REDIRECT_ENABLE = 'redirect_enable';

	/**
	 * A single redirect rule was disabled.
	 */
	public const REDIRECT_DISABLE = 'redirect_disable';

	/**
	 * A redirect group was created.
	 */
	public const REDIRECT_GROUP_CREATE = 'redirect_group_create';

	/**
	 * A redirect group's name/module changed.
	 */
	public const REDIRECT_GROUP_UPDATE = 'redirect_group_update';

	/**
	 * A redirect group was permanently deleted.
	 */
	public const REDIRECT_GROUP_DELETE = 'redirect_group_delete';

	/**
	 * A redirect group was enabled.
	 */
	public const REDIRECT_GROUP_ENABLE = 'redirect_group_enable';

	/**
	 * A redirect group was disabled.
	 */
	public const REDIRECT_GROUP_DISABLE = 'redirect_group_disable';

	/**
	 * A TablePress table was created (PM-170, Sprint 11).
	 */
	public const TABLE_CREATE = 'table_create';

	/**
	 * A TablePress table's content or settings changed.
	 */
	public const TABLE_UPDATE = 'table_update';

	/**
	 * A TablePress table was deleted.
	 */
	public const TABLE_DELETE = 'table_delete';

	/**
	 * A TablePress table was duplicated.
	 */
	public const TABLE_DUPLICATE = 'table_duplicate';

	/**
	 * A bbPress forum was created (PM-171, Sprint 11).
	 */
	public const FORUM_CREATE = 'forum_create';

	/**
	 * A bbPress forum's content or settings changed.
	 */
	public const FORUM_UPDATE = 'forum_update';

	/**
	 * A bbPress forum was moved to trash.
	 */
	public const FORUM_TRASH = 'forum_trash';

	/**
	 * A bbPress forum was restored from trash.
	 */
	public const FORUM_RESTORE = 'forum_restore';

	/**
	 * A bbPress forum was permanently deleted.
	 */
	public const FORUM_DELETE = 'forum_delete';

	/**
	 * A bbPress topic was created.
	 */
	public const TOPIC_CREATE = 'topic_create';

	/**
	 * A bbPress topic's content changed.
	 */
	public const TOPIC_UPDATE = 'topic_update';

	/**
	 * A bbPress topic was closed.
	 */
	public const TOPIC_CLOSE = 'topic_close';

	/**
	 * A bbPress topic was reopened.
	 */
	public const TOPIC_OPEN = 'topic_open';

	/**
	 * A bbPress topic was stuck (pinned).
	 */
	public const TOPIC_STICK = 'topic_stick';

	/**
	 * A bbPress topic was unstuck (unpinned).
	 */
	public const TOPIC_UNSTICK = 'topic_unstick';

	/**
	 * A bbPress topic was moved to trash.
	 */
	public const TOPIC_TRASH = 'topic_trash';

	/**
	 * A bbPress topic was restored from trash.
	 */
	public const TOPIC_RESTORE = 'topic_restore';

	/**
	 * A bbPress topic was permanently deleted.
	 */
	public const TOPIC_DELETE = 'topic_delete';

	/**
	 * A bbPress reply was created.
	 */
	public const REPLY_CREATE = 'reply_create';

	/**
	 * A bbPress reply's content changed.
	 */
	public const REPLY_UPDATE = 'reply_update';

	/**
	 * A bbPress reply was moved to trash.
	 */
	public const REPLY_TRASH = 'reply_trash';

	/**
	 * A bbPress reply was restored from trash.
	 */
	public const REPLY_RESTORE = 'reply_restore';

	/**
	 * A bbPress reply was permanently deleted.
	 */
	public const REPLY_DELETE = 'reply_delete';

	/**
	 * WP 2FA enforcement was turned on, or its enforcement mode changed
	 * between "all users" / "specific roles or users" (PM-172, Sprint 11 -
	 * expanded to full WP Activity Log parity same sprint, at explicit
	 * user request). Matches WP Activity Log's alert 7800.
	 */
	public const TWO_FACTOR_POLICY_ENFORCE = 'two_factor_policy_enforce';

	/**
	 * WP 2FA enforcement was turned off entirely (`enforcement-policy`
	 * set to `do-not-enforce`). Matches WP Activity Log's alert 7801.
	 */
	public const TWO_FACTOR_POLICY_DISABLE = 'two_factor_policy_disable';

	/**
	 * WP 2FA's enforced-roles or enforced-users list changed. Matches WP
	 * Activity Log's alert 7802.
	 */
	public const TWO_FACTOR_ENFORCED_LIST_CHANGE = 'two_factor_enforced_list_change';

	/**
	 * WP 2FA's excluded-roles or excluded-users list changed. Matches WP
	 * Activity Log's alert 7803.
	 */
	public const TWO_FACTOR_EXCLUDED_LIST_CHANGE = 'two_factor_excluded_list_change';

	/**
	 * A site-wide allowed-2FA-method flag (TOTP/email/passkeys/backup
	 * codes) was toggled in the policy. Matches WP Activity Log's alert
	 * 7804.
	 */
	public const TWO_FACTOR_POLICY_METHOD_TOGGLE = 'two_factor_policy_method_toggle';

	/**
	 * WP 2FA's "require 2FA for password resets" setting was toggled.
	 * Matches WP Activity Log's alert 7807.
	 */
	public const TWO_FACTOR_PASSWORD_RESET_TOGGLE = 'two_factor_password_reset_toggle';

	/**
	 * A user configured a WP 2FA method for the first time (no prior
	 * method was set). Matches WP Activity Log's alert 7808.
	 */
	public const TWO_FACTOR_METHOD_CONFIGURE = 'two_factor_method_configure';

	/**
	 * A user changed their already-configured WP 2FA method to a
	 * different one. Matches WP Activity Log's alert 7809.
	 */
	public const TWO_FACTOR_METHOD_CHANGE = 'two_factor_method_change';

	/**
	 * A user's WP 2FA method was removed - either self-service or an
	 * admin-initiated reset for another user. Matches WP Activity Log's
	 * alert 7810.
	 */
	public const TWO_FACTOR_METHOD_DISABLE = 'two_factor_method_disable';

	/**
	 * A user was locked out for not configuring WP 2FA within the grace
	 * period. Matches WP Activity Log's alert 7811.
	 */
	public const TWO_FACTOR_USER_LOCKED = 'two_factor_user_locked';

	/**
	 * A previously WP-2FA-locked user was unlocked. Matches WP Activity
	 * Log's alert 7812.
	 */
	public const TWO_FACTOR_USER_UNLOCKED = 'two_factor_user_unlocked';

	// --- Ultimate Member actions (PM-174, Sprint 12). ---

	/**
	 * A new member registered through an Ultimate Member registration
	 * form (or was created with a UM role from wp-admin's "Add New User"
	 * screen).
	 */
	public const MEMBER_REGISTER = 'member_register';

	/**
	 * A member's Ultimate Member profile fields changed - self-edit or
	 * admin-edit, diffed as one batch.
	 */
	public const MEMBER_PROFILE_UPDATE = 'member_profile_update';

	/**
	 * A member's Ultimate Member role assignment was changed by an admin.
	 */
	public const MEMBER_ROLE_CHANGE = 'member_role_change';

	// --- Wordfence actions (PM-175, Sprint 12). ---

	/**
	 * A login attempt using a password known to exist in a public data
	 * breach was blocked (Wordfence's own `breachLogin` security event).
	 */
	public const LOGIN_BREACH = 'login_breach';

	/**
	 * An IP was locked out of login after repeated failed attempts
	 * (Wordfence's own `loginLockout` security event).
	 */
	public const LOGIN_LOCKOUT = 'login_lockout';

	/**
	 * An IP was blocked by Wordfence's firewall - rate-limit-triggered
	 * (`security_event('block')`) or a manual/permanent/pattern block
	 * rule being created (`wordfence_created_ip_pattern_block`).
	 */
	public const FIREWALL_BLOCK = 'firewall_block';

	/**
	 * An IP was throttled (rather than fully blocked) by Wordfence's
	 * rate-limiting engine (`security_event('throttle')`).
	 */
	public const FIREWALL_THROTTLE = 'firewall_throttle';

	/**
	 * A previously-created Wordfence block rule was manually removed.
	 */
	public const FIREWALL_UNBLOCK = 'firewall_unblock';

	/**
	 * Wordfence's country-blocking list changed.
	 */
	public const COUNTRY_BLOCK_CHANGE = 'country_block_change';

	/**
	 * Wordfence detected an aggregate increase in attack rate against the
	 * site (`security_event('increasedAttackRate')`).
	 */
	public const ATTACK_RATE_INCREASE = 'attack_rate_increase';

	/**
	 * A Wordfence firewall or scan configuration setting changed - one
	 * shared action across several distinct settings, matching
	 * `WPSettingsActivityLogger`'s existing pattern.
	 */
	public const SECURITY_SETTINGS_CHANGE = 'security_settings_change';

	// --- Tutor LMS actions (PM-176, Sprint 12). ---

	/**
	 * A Tutor LMS course was created.
	 */
	public const COURSE_CREATE = 'course_create';

	/**
	 * A Tutor LMS course's content or settings changed.
	 */
	public const COURSE_UPDATE = 'course_update';

	/**
	 * A Tutor LMS course was moved to trash.
	 */
	public const COURSE_TRASH = 'course_trash';

	/**
	 * A Tutor LMS course was restored from trash.
	 */
	public const COURSE_RESTORE = 'course_restore';

	/**
	 * A Tutor LMS course was permanently deleted.
	 */
	public const COURSE_DELETE = 'course_delete';

	/**
	 * A Tutor LMS lesson was created.
	 */
	public const LESSON_CREATE = 'lesson_create';

	/**
	 * A Tutor LMS lesson's content changed.
	 */
	public const LESSON_UPDATE = 'lesson_update';

	/**
	 * A Tutor LMS lesson was moved to trash.
	 */
	public const LESSON_TRASH = 'lesson_trash';

	/**
	 * A Tutor LMS lesson was restored from trash.
	 */
	public const LESSON_RESTORE = 'lesson_restore';

	/**
	 * A Tutor LMS lesson was permanently deleted.
	 */
	public const LESSON_DELETE = 'lesson_delete';

	/**
	 * A Tutor LMS course topic was created.
	 */
	public const COURSE_TOPIC_CREATE = 'course_topic_create';

	/**
	 * A Tutor LMS course topic's title/content changed.
	 */
	public const COURSE_TOPIC_UPDATE = 'course_topic_update';

	/**
	 * A Tutor LMS course topic was moved to trash.
	 */
	public const COURSE_TOPIC_TRASH = 'course_topic_trash';

	/**
	 * A Tutor LMS course topic was restored from trash.
	 */
	public const COURSE_TOPIC_RESTORE = 'course_topic_restore';

	/**
	 * A Tutor LMS course topic was permanently deleted.
	 */
	public const COURSE_TOPIC_DELETE = 'course_topic_delete';

	/**
	 * A Tutor LMS quiz was created.
	 */
	public const QUIZ_CREATE = 'quiz_create';

	/**
	 * A Tutor LMS quiz's content/questions/settings changed.
	 */
	public const QUIZ_UPDATE = 'quiz_update';

	/**
	 * A Tutor LMS quiz was moved to trash.
	 */
	public const QUIZ_TRASH = 'quiz_trash';

	/**
	 * A Tutor LMS quiz was restored from trash.
	 */
	public const QUIZ_RESTORE = 'quiz_restore';

	/**
	 * A Tutor LMS quiz was permanently deleted.
	 */
	public const QUIZ_DELETE = 'quiz_delete';

	/**
	 * A student's course enrollment was granted (immediately, or via a
	 * payment being confirmed after a pending enrollment).
	 */
	public const ENROLLMENT_GRANT = 'enrollment_grant';

	/**
	 * A student's course enrollment was created in a pending (awaiting
	 * payment) state - distinct from `ENROLLMENT_GRANT`, which is only
	 * logged once the student actually has access.
	 */
	public const ENROLLMENT_PENDING = 'enrollment_pending';

	/**
	 * A student's course enrollment was removed (cancelled or fully
	 * deleted).
	 */
	public const ENROLLMENT_REMOVE = 'enrollment_remove';

	/**
	 * A student completed a lesson.
	 */
	public const LESSON_COMPLETE = 'lesson_complete';

	/**
	 * A student completed (finished) a quiz attempt.
	 */
	public const QUIZ_COMPLETE = 'quiz_complete';

	/**
	 * A student completed an entire course.
	 */
	public const COURSE_COMPLETE = 'course_complete';

	/**
	 * Resolve action label from known keys with fallback.
	 *
	 * @param string $action_key Action key.
	 * @return string
	 */
	public static function resolve_label( string $action_key ): string {

		$labels = self::get_labels();

		if ( isset( $labels[ $action_key ] ) ) {
			return $labels[ $action_key ];
		}

		return ucwords(
			str_replace(
				'_',
				' ',
				$action_key
			)
		);
	}

	/**
	 * Get memoized action labels map.
	 *
	 * @return array
	 */
	private static function get_labels(): array {

		static $labels = null;

		if ( null !== $labels ) {
			return $labels;
		}

		$labels = array(
			self::CREATE                                 => __( 'Create', 'pastmark' ),
			self::UPDATE                                 => __( 'Update', 'pastmark' ),
			self::DELETE                                 => __( 'Delete', 'pastmark' ),
			self::RESTORE                                => __( 'Restore', 'pastmark' ),
			self::LOGIN                                  => __( 'Login', 'pastmark' ),
			self::LOGOUT                                 => __( 'Logout', 'pastmark' ),
			self::FAILED_LOGIN                           => __( 'Failed Login', 'pastmark' ),
			self::SWITCH_USER                            => __( 'Switch User', 'pastmark' ),
			self::REGISTER                               => __( 'Register', 'pastmark' ),
			self::ROLE_CHANGE                            => __( 'Role Change', 'pastmark' ),
			self::STATUS_CHANGE                          => __( 'Status Change', 'pastmark' ),
			self::ACTIVATE                               => __( 'Activate', 'pastmark' ),
			self::DEACTIVATE                             => __( 'Deactivate', 'pastmark' ),
			self::SWITCH                                 => __( 'Switch', 'pastmark' ),
			self::ITEM_UPDATE                            => __( 'Item Update', 'pastmark' ),
			self::UPDATE_CHECK                           => __( 'Update Check', 'pastmark' ),
			self::AUTHOR_CHANGE                          => __( 'Author Change', 'pastmark' ),
			self::SLUG_CHANGE                            => __( 'Slug Change', 'pastmark' ),
			self::VISIBILITY_CHANGE                      => __( 'Visibility Change', 'pastmark' ),
			self::DATE_CHANGE                            => __( 'Date Change', 'pastmark' ),
			self::STICKY_CHANGE                          => __( 'Sticky Change', 'pastmark' ),
			self::PARENT_CHANGE                          => __( 'Parent Change', 'pastmark' ),
			self::TEMPLATE_CHANGE                        => __( 'Template Change', 'pastmark' ),
			self::CONTENT_CHANGE                         => __( 'Content Change', 'pastmark' ),
			self::REPLY                                  => __( 'Reply', 'pastmark' ),
			self::SPAM_CHANGE                            => __( 'Spam Change', 'pastmark' ),
			self::PASSWORD_CHANGE                        => __( 'Password Change', 'pastmark' ),
			self::EMAIL_CHANGE                           => __( 'Email Change', 'pastmark' ),
			self::PASSWORD_RESET_SENT                    => __( 'Password Reset Sent', 'pastmark' ),
			self::SUPER_ADMIN_CHANGE                     => __( 'Super Admin Change', 'pastmark' ),
			self::ADD_TO_SITE                            => __( 'Added To Site', 'pastmark' ),
			self::REMOVE_FROM_SITE                       => __( 'Removed From Site', 'pastmark' ),
			self::USER_META_ADD                          => __( 'Custom Field Added', 'pastmark' ),
			self::USER_META_UPDATE                       => __( 'Custom Field Updated', 'pastmark' ),
			self::USER_META_DELETE                       => __( 'Custom Field Deleted', 'pastmark' ),
			self::APP_PASSWORD_CREATE                    => __( 'Application Password Created', 'pastmark' ),
			self::APP_PASSWORD_REVOKE                    => __( 'Application Password Revoked', 'pastmark' ),
			self::FEATURED_IMAGE_CHANGE                  => __( 'Featured Image Change', 'pastmark' ),
			self::SITE_ICON_CHANGE                       => __( 'Site Icon Change', 'pastmark' ),
			self::INSTALL                                => __( 'Install', 'pastmark' ),
			self::INSTALL_FAILED                         => __( 'Install Failed', 'pastmark' ),
			self::AUTO_UPDATE_CHANGE                     => __( 'Automatic Update Change', 'pastmark' ),
			self::FILE_EDIT                              => __( 'File Edit', 'pastmark' ),
			self::CORE_UPDATE                            => __( 'Core Update', 'pastmark' ),

			self::PRODUCT_CREATE                         => __( 'Product Create', 'pastmark' ),
			self::PRODUCT_PUBLISH                        => __( 'Product Publish', 'pastmark' ),
			self::PRODUCT_TRASH                          => __( 'Product Trash', 'pastmark' ),
			self::PRODUCT_DELETE                         => __( 'Product Delete', 'pastmark' ),
			self::PRODUCT_RESTORE                        => __( 'Product Restore', 'pastmark' ),
			self::PRODUCT_STATUS_CHANGE                  => __( 'Product Status Change', 'pastmark' ),
			self::PRODUCT_RENAME                         => __( 'Product Rename', 'pastmark' ),
			self::PRODUCT_CATEGORY_CHANGE                => __( 'Product Category Change', 'pastmark' ),
			self::PRODUCT_VISIBILITY_CHANGE              => __( 'Product Visibility Change', 'pastmark' ),
			self::PRODUCT_SKU_CHANGE                     => __( 'Product SKU Change', 'pastmark' ),
			self::PRODUCT_PRICE_CHANGE                   => __( 'Product Price Change', 'pastmark' ),
			self::PRODUCT_STOCK_STATUS_CHANGE            => __( 'Product Stock Status Change', 'pastmark' ),
			self::PRODUCT_STOCK_QTY_CHANGE               => __( 'Product Stock Quantity Change', 'pastmark' ),
			self::PRODUCT_STOCK_AUTO_CHANGE              => __( 'Product Stock Auto-Change', 'pastmark' ),
			self::PRODUCT_CATEGORY_CREATE                => __( 'Product Category Create', 'pastmark' ),
			self::PRODUCT_CATEGORY_DELETE                => __( 'Product Category Delete', 'pastmark' ),

			self::ORDER_PLACED                           => __( 'Order Placed', 'pastmark' ),
			self::ORDER_STATUS_CHANGE                    => __( 'Order Status Change', 'pastmark' ),
			self::ORDER_TRASH                            => __( 'Order Trash', 'pastmark' ),
			self::ORDER_RESTORE                          => __( 'Order Restore', 'pastmark' ),
			self::ORDER_DELETE                           => __( 'Order Delete', 'pastmark' ),
			self::ORDER_EDIT                             => __( 'Order Edit', 'pastmark' ),
			self::ORDER_REFUND                           => __( 'Order Refund', 'pastmark' ),
			self::ORDER_NOTE_ADD                         => __( 'Order Note Add', 'pastmark' ),
			self::ORDER_NOTE_DELETE                      => __( 'Order Note Delete', 'pastmark' ),

			self::COUPON_CREATE                          => __( 'Coupon Create', 'pastmark' ),
			self::COUPON_AMOUNT_CHANGE                   => __( 'Coupon Amount Change', 'pastmark' ),
			self::COUPON_STATUS_CHANGE                   => __( 'Coupon Status Change', 'pastmark' ),
			self::COUPON_RENAME                          => __( 'Coupon Rename', 'pastmark' ),
			self::COUPON_TRASH                           => __( 'Coupon Trash', 'pastmark' ),
			self::COUPON_RESTORE                         => __( 'Coupon Restore', 'pastmark' ),
			self::COUPON_DELETE                          => __( 'Coupon Delete', 'pastmark' ),

			self::REVIEW_CREATE                          => __( 'Review Create', 'pastmark' ),
			self::REVIEW_APPROVE                         => __( 'Review Approve', 'pastmark' ),
			self::REVIEW_UNAPPROVE                       => __( 'Review Unapprove', 'pastmark' ),
			self::REVIEW_SPAM                            => __( 'Review Spam', 'pastmark' ),
			self::REVIEW_TRASH                           => __( 'Review Trash', 'pastmark' ),
			self::REVIEW_DELETE                          => __( 'Review Delete', 'pastmark' ),

			self::FIELD_GROUP_CREATE                     => __( 'Field Group Create', 'pastmark' ),
			self::FIELD_GROUP_UPDATE                     => __( 'Field Group Update', 'pastmark' ),
			self::FIELD_GROUP_DELETE                     => __( 'Field Group Delete', 'pastmark' ),
			self::FIELD_GROUP_TRASH                      => __( 'Field Group Trash', 'pastmark' ),
			self::FIELD_GROUP_RESTORE                    => __( 'Field Group Restore', 'pastmark' ),
			self::FIELD_GROUP_DUPLICATE                  => __( 'Field Group Duplicate', 'pastmark' ),
			self::FIELD_CREATE                           => __( 'Field Create', 'pastmark' ),
			self::FIELD_UPDATE                           => __( 'Field Update', 'pastmark' ),
			self::FIELD_DELETE                           => __( 'Field Delete', 'pastmark' ),

			self::FORM_CREATE                            => __( 'Form Create', 'pastmark' ),
			self::FORM_UPDATE                            => __( 'Form Update', 'pastmark' ),
			self::FORM_DELETE                            => __( 'Form Delete', 'pastmark' ),
			self::FORM_TRASH                             => __( 'Form Trash', 'pastmark' ),
			self::FORM_RESTORE                           => __( 'Form Restore', 'pastmark' ),
			self::FORM_DUPLICATE                         => __( 'Form Duplicate', 'pastmark' ),
			self::ENTRY_CREATE                           => __( 'Entry Create', 'pastmark' ),
			self::NOTIFICATION_FAILED                    => __( 'Notification Failed', 'pastmark' ),

			self::FORM_ACTIVATE                          => __( 'Form Activate', 'pastmark' ),
			self::FORM_DEACTIVATE                        => __( 'Form Deactivate', 'pastmark' ),
			self::FORM_IMPORT                            => __( 'Form Import', 'pastmark' ),
			self::FORM_EXPORT                            => __( 'Form Export', 'pastmark' ),
			self::FORM_SETTINGS_CHANGE                   => __( 'Form Settings Change', 'pastmark' ),
			self::CONFIRMATION_CREATE                    => __( 'Confirmation Create', 'pastmark' ),
			self::CONFIRMATION_UPDATE                    => __( 'Confirmation Update', 'pastmark' ),
			self::CONFIRMATION_DELETE                    => __( 'Confirmation Delete', 'pastmark' ),
			self::CONFIRMATION_ACTIVATE                  => __( 'Confirmation Activate', 'pastmark' ),
			self::CONFIRMATION_DEACTIVATE                => __( 'Confirmation Deactivate', 'pastmark' ),
			self::NOTIFICATION_CREATE                    => __( 'Notification Create', 'pastmark' ),
			self::NOTIFICATION_UPDATE                    => __( 'Notification Update', 'pastmark' ),
			self::NOTIFICATION_DELETE                    => __( 'Notification Delete', 'pastmark' ),
			self::NOTIFICATION_ACTIVATE                  => __( 'Notification Activate', 'pastmark' ),
			self::NOTIFICATION_DEACTIVATE                => __( 'Notification Deactivate', 'pastmark' ),
			self::ENTRY_STAR                             => __( 'Entry Star', 'pastmark' ),
			self::ENTRY_UNSTAR                           => __( 'Entry Unstar', 'pastmark' ),
			self::ENTRY_READ                             => __( 'Entry Read', 'pastmark' ),
			self::ENTRY_UNREAD                           => __( 'Entry Unread', 'pastmark' ),
			self::ENTRY_TRASH                            => __( 'Entry Trash', 'pastmark' ),
			self::ENTRY_RESTORE                          => __( 'Entry Restore', 'pastmark' ),
			self::ENTRY_DELETE                           => __( 'Entry Delete', 'pastmark' ),
			self::ENTRY_EXPORT                           => __( 'Entry Export', 'pastmark' ),
			self::ENTRY_NOTE_ADD                         => __( 'Entry Note Add', 'pastmark' ),
			self::ENTRY_NOTE_DELETE                      => __( 'Entry Note Delete', 'pastmark' ),
			self::ENTRY_EDIT                             => __( 'Entry Edit', 'pastmark' ),

			self::FORM_RENAME                            => __( 'Form Rename', 'pastmark' ),
			self::SERVICE_INTEGRATION_CHANGE             => __( 'Service Integration Change', 'pastmark' ),
			self::ADDON_ACTIVATE                         => __( 'Addon Activate', 'pastmark' ),
			self::ADDON_DEACTIVATE                       => __( 'Addon Deactivate', 'pastmark' ),

			self::SEO_META_UPDATE                        => __( 'SEO Meta Update', 'pastmark' ),

			self::SEO_TITLE_CHANGE                       => __( 'SEO Title Change', 'pastmark' ),
			self::SEO_METADESC_CHANGE                    => __( 'SEO Meta Description Change', 'pastmark' ),
			self::SEO_FOCUS_KEYWORD_CHANGE               => __( 'SEO Focus Keyword Change', 'pastmark' ),
			self::SEO_NOINDEX_CHANGE                     => __( 'SEO Search Visibility Change', 'pastmark' ),
			self::SEO_NOFOLLOW_CHANGE                    => __( 'SEO Link-Following Change', 'pastmark' ),
			self::SEO_ADVANCED_ROBOTS_CHANGE             => __( 'SEO Advanced Meta Robots Change', 'pastmark' ),
			self::SEO_CANONICAL_URL_CHANGE               => __( 'SEO Canonical URL Change', 'pastmark' ),
			self::SEO_CORNERSTONE_CHANGE                 => __( 'SEO Cornerstone Content Change', 'pastmark' ),
			self::SEO_BREADCRUMB_TITLE_CHANGE            => __( 'SEO Breadcrumb Title Change', 'pastmark' ),
			self::SEO_SCHEMA_PAGE_TYPE_CHANGE            => __( 'SEO Schema Page Type Change (Post)', 'pastmark' ),
			self::SEO_SCHEMA_ARTICLE_TYPE_CHANGE         => __( 'SEO Schema Article Type Change (Post)', 'pastmark' ),

			self::SEO_TITLE_SEPARATOR_CHANGE             => __( 'SEO Title Separator Change', 'pastmark' ),
			self::SEO_KNOWLEDGE_GRAPH_TYPE_CHANGE        => __( 'SEO Knowledge Graph Type Change', 'pastmark' ),
			self::SEO_POST_TYPE_VISIBILITY_CHANGE        => __( 'SEO Post Type Visibility Change', 'pastmark' ),
			self::SEO_POST_TYPE_TITLE_TEMPLATE_CHANGE    => __( 'SEO Post Type Title Template Change', 'pastmark' ),
			self::SEO_POST_TYPE_METADESC_TEMPLATE_CHANGE => __( 'SEO Post Type Meta Description Template Change', 'pastmark' ),
			self::SEO_POST_TYPE_METABOX_TOGGLE           => __( 'SEO Post Type Meta Box Toggle', 'pastmark' ),
			self::SEO_AUTHOR_ADVANCED_META_TOGGLE        => __( 'SEO Author Advanced Meta Toggle', 'pastmark' ),
			self::SEO_ATTACHMENT_REDIRECT_TOGGLE         => __( 'SEO Attachment Redirect Toggle', 'pastmark' ),
			self::SEO_USAGE_TRACKING_TOGGLE              => __( 'SEO Usage Tracking Toggle', 'pastmark' ),
			self::SEO_INTEGRATION_TOGGLE                 => __( 'SEO Integration Toggle', 'pastmark' ),
			self::SEO_SOCIAL_PROFILE_CHANGE              => __( 'SEO Social Profile Change', 'pastmark' ),
			self::SEO_TAXONOMY_VISIBILITY_CHANGE         => __( 'SEO Taxonomy Visibility Change', 'pastmark' ),
			self::SEO_TAXONOMY_TITLE_TEMPLATE_CHANGE     => __( 'SEO Taxonomy Title Template Change', 'pastmark' ),
			self::SEO_TAXONOMY_METADESC_TEMPLATE_CHANGE  => __( 'SEO Taxonomy Meta Description Template Change', 'pastmark' ),
			self::SEO_ARCHIVE_TOGGLE                     => __( 'SEO Archive Toggle', 'pastmark' ),
			self::SEO_ARCHIVE_VISIBILITY_CHANGE          => __( 'SEO Archive Visibility Change', 'pastmark' ),
			self::SEO_ARCHIVE_TITLE_TEMPLATE_CHANGE      => __( 'SEO Archive Title Template Change', 'pastmark' ),
			self::SEO_ARCHIVE_METADESC_TEMPLATE_CHANGE   => __( 'SEO Archive Meta Description Template Change', 'pastmark' ),
			self::SEO_TAXONOMY_METABOX_TOGGLE            => __( 'SEO Taxonomy Meta Box Toggle', 'pastmark' ),
			self::SEO_VERIFICATION_CODE_CHANGE           => __( 'SEO Verification Code Change', 'pastmark' ),
			self::SEO_SOCIAL_DEFAULT_IMAGE_CHANGE        => __( 'SEO Social Default Image Change', 'pastmark' ),
			self::SEO_TWITTER_CARD_TYPE_CHANGE           => __( 'SEO Twitter Card Type Change', 'pastmark' ),
			self::SEO_PINTEREST_VERIFICATION_CHANGE      => __( 'SEO Pinterest Verification Change', 'pastmark' ),
			self::SEO_DEFAULT_SCHEMA_PAGE_TYPE_CHANGE    => __( 'SEO Default Schema Page Type Change', 'pastmark' ),
			self::SEO_DEFAULT_SCHEMA_ARTICLE_TYPE_CHANGE => __( 'SEO Default Schema Article Type Change', 'pastmark' ),
			self::SEO_FEATURE_TOGGLE                     => __( 'SEO Feature Toggle', 'pastmark' ),
			self::SEO_CRAWL_OPTIMIZATION_CHANGE          => __( 'SEO Crawl Optimization Change', 'pastmark' ),
			self::SEO_WEBSITE_NAME_CHANGE                => __( 'SEO Website Name Change', 'pastmark' ),
			self::SEO_ALTERNATE_WEBSITE_NAME_CHANGE      => __( 'SEO Alternate Website Name Change', 'pastmark' ),
			self::SEO_ORGANIZATION_NAME_CHANGE           => __( 'SEO Organization Name Change', 'pastmark' ),
			self::SEO_ALTERNATE_ORGANIZATION_NAME_CHANGE => __( 'SEO Alternate Organization Name Change', 'pastmark' ),
			self::SEO_ORGANIZATION_LOGO_CHANGE           => __( 'SEO Organization Logo Change', 'pastmark' ),
			self::SEO_KNOWLEDGE_GRAPH_USER_CHANGE        => __( 'SEO Knowledge Graph User Change', 'pastmark' ),
			self::SEO_PERSONAL_LOGO_CHANGE               => __( 'SEO Personal Logo Change', 'pastmark' ),

			self::SEO_ROBOTS_NOARCHIVE_CHANGE            => __( 'SEO Robots No-Archive Change', 'pastmark' ),
			self::SEO_ROBOTS_NOIMAGEINDEX_CHANGE         => __( 'SEO Robots No-Image-Index Change', 'pastmark' ),
			self::SEO_ROBOTS_NOSNIPPET_CHANGE            => __( 'SEO Robots No-Snippet Change', 'pastmark' ),
			self::SEO_MAX_SNIPPET_LENGTH_CHANGE          => __( 'SEO Max Snippet Length Change', 'pastmark' ),
			self::SEO_MAX_VIDEO_PREVIEW_CHANGE           => __( 'SEO Max Video Preview Change', 'pastmark' ),
			self::SEO_MAX_IMAGE_PREVIEW_CHANGE           => __( 'SEO Max Image Preview Change', 'pastmark' ),
			self::SEO_MODULE_TOGGLE                      => __( 'SEO Module Toggle', 'pastmark' ),

			self::REDIRECT_CREATE                        => __( 'Redirect Create', 'pastmark' ),
			self::REDIRECT_UPDATE                        => __( 'Redirect Update', 'pastmark' ),
			self::REDIRECT_DELETE                        => __( 'Redirect Delete', 'pastmark' ),
			self::REDIRECT_ENABLE                        => __( 'Redirect Enable', 'pastmark' ),
			self::REDIRECT_DISABLE                       => __( 'Redirect Disable', 'pastmark' ),
			self::REDIRECT_GROUP_CREATE                  => __( 'Redirect Group Create', 'pastmark' ),
			self::REDIRECT_GROUP_UPDATE                  => __( 'Redirect Group Update', 'pastmark' ),
			self::REDIRECT_GROUP_DELETE                  => __( 'Redirect Group Delete', 'pastmark' ),
			self::REDIRECT_GROUP_ENABLE                  => __( 'Redirect Group Enable', 'pastmark' ),
			self::REDIRECT_GROUP_DISABLE                 => __( 'Redirect Group Disable', 'pastmark' ),

			self::TABLE_CREATE                           => __( 'Table Create', 'pastmark' ),
			self::TABLE_UPDATE                           => __( 'Table Update', 'pastmark' ),
			self::TABLE_DELETE                           => __( 'Table Delete', 'pastmark' ),
			self::TABLE_DUPLICATE                        => __( 'Table Duplicate', 'pastmark' ),

			self::FORUM_CREATE                           => __( 'Forum Create', 'pastmark' ),
			self::FORUM_UPDATE                           => __( 'Forum Update', 'pastmark' ),
			self::FORUM_TRASH                            => __( 'Forum Trash', 'pastmark' ),
			self::FORUM_RESTORE                          => __( 'Forum Restore', 'pastmark' ),
			self::FORUM_DELETE                           => __( 'Forum Delete', 'pastmark' ),
			self::TOPIC_CREATE                           => __( 'Topic Create', 'pastmark' ),
			self::TOPIC_UPDATE                           => __( 'Topic Update', 'pastmark' ),
			self::TOPIC_CLOSE                            => __( 'Topic Close', 'pastmark' ),
			self::TOPIC_OPEN                             => __( 'Topic Open', 'pastmark' ),
			self::TOPIC_STICK                            => __( 'Topic Stick', 'pastmark' ),
			self::TOPIC_UNSTICK                          => __( 'Topic Unstick', 'pastmark' ),
			self::TOPIC_TRASH                            => __( 'Topic Trash', 'pastmark' ),
			self::TOPIC_RESTORE                          => __( 'Topic Restore', 'pastmark' ),
			self::TOPIC_DELETE                           => __( 'Topic Delete', 'pastmark' ),
			self::REPLY_CREATE                           => __( 'Reply Create', 'pastmark' ),
			self::REPLY_UPDATE                           => __( 'Reply Update', 'pastmark' ),
			self::REPLY_TRASH                            => __( 'Reply Trash', 'pastmark' ),
			self::REPLY_RESTORE                          => __( 'Reply Restore', 'pastmark' ),
			self::REPLY_DELETE                           => __( 'Reply Delete', 'pastmark' ),
			self::TWO_FACTOR_POLICY_ENFORCE              => __( 'Two-Factor Policy Enforce', 'pastmark' ),
			self::TWO_FACTOR_POLICY_DISABLE              => __( 'Two-Factor Policy Disable', 'pastmark' ),
			self::TWO_FACTOR_ENFORCED_LIST_CHANGE        => __( 'Two-Factor Enforced List Change', 'pastmark' ),
			self::TWO_FACTOR_EXCLUDED_LIST_CHANGE        => __( 'Two-Factor Excluded List Change', 'pastmark' ),
			self::TWO_FACTOR_POLICY_METHOD_TOGGLE        => __( 'Two-Factor Policy Method Toggle', 'pastmark' ),
			self::TWO_FACTOR_PASSWORD_RESET_TOGGLE       => __( 'Two-Factor Password Reset Toggle', 'pastmark' ),
			self::TWO_FACTOR_METHOD_CONFIGURE            => __( 'Two-Factor Method Configure', 'pastmark' ),
			self::TWO_FACTOR_METHOD_CHANGE               => __( 'Two-Factor Method Change', 'pastmark' ),
			self::TWO_FACTOR_METHOD_DISABLE              => __( 'Two-Factor Method Disable', 'pastmark' ),
			self::TWO_FACTOR_USER_LOCKED                 => __( 'Two-Factor User Locked', 'pastmark' ),
			self::TWO_FACTOR_USER_UNLOCKED               => __( 'Two-Factor User Unlocked', 'pastmark' ),

			self::MEMBER_REGISTER                        => __( 'Member Register', 'pastmark' ),
			self::MEMBER_PROFILE_UPDATE                  => __( 'Member Profile Update', 'pastmark' ),
			self::MEMBER_ROLE_CHANGE                     => __( 'Member Role Change', 'pastmark' ),

			self::LOGIN_BREACH                           => __( 'Login Breach', 'pastmark' ),
			self::LOGIN_LOCKOUT                          => __( 'Login Lockout', 'pastmark' ),
			self::FIREWALL_BLOCK                         => __( 'Firewall Block', 'pastmark' ),
			self::FIREWALL_THROTTLE                      => __( 'Firewall Throttle', 'pastmark' ),
			self::FIREWALL_UNBLOCK                       => __( 'Firewall Unblock', 'pastmark' ),
			self::COUNTRY_BLOCK_CHANGE                   => __( 'Country Block Change', 'pastmark' ),
			self::ATTACK_RATE_INCREASE                   => __( 'Attack Rate Increase', 'pastmark' ),
			self::SECURITY_SETTINGS_CHANGE               => __( 'Security Settings Change', 'pastmark' ),

			self::COURSE_CREATE                          => __( 'Course Create', 'pastmark' ),
			self::COURSE_UPDATE                          => __( 'Course Update', 'pastmark' ),
			self::COURSE_TRASH                           => __( 'Course Trash', 'pastmark' ),
			self::COURSE_RESTORE                         => __( 'Course Restore', 'pastmark' ),
			self::COURSE_DELETE                          => __( 'Course Delete', 'pastmark' ),
			self::LESSON_CREATE                          => __( 'Lesson Create', 'pastmark' ),
			self::LESSON_UPDATE                          => __( 'Lesson Update', 'pastmark' ),
			self::LESSON_TRASH                           => __( 'Lesson Trash', 'pastmark' ),
			self::LESSON_RESTORE                         => __( 'Lesson Restore', 'pastmark' ),
			self::LESSON_DELETE                          => __( 'Lesson Delete', 'pastmark' ),
			self::COURSE_TOPIC_CREATE                    => __( 'Course Topic Create', 'pastmark' ),
			self::COURSE_TOPIC_UPDATE                    => __( 'Course Topic Update', 'pastmark' ),
			self::COURSE_TOPIC_TRASH                     => __( 'Course Topic Trash', 'pastmark' ),
			self::COURSE_TOPIC_RESTORE                   => __( 'Course Topic Restore', 'pastmark' ),
			self::COURSE_TOPIC_DELETE                    => __( 'Course Topic Delete', 'pastmark' ),
			self::QUIZ_CREATE                            => __( 'Quiz Create', 'pastmark' ),
			self::QUIZ_UPDATE                            => __( 'Quiz Update', 'pastmark' ),
			self::QUIZ_TRASH                             => __( 'Quiz Trash', 'pastmark' ),
			self::QUIZ_RESTORE                           => __( 'Quiz Restore', 'pastmark' ),
			self::QUIZ_DELETE                            => __( 'Quiz Delete', 'pastmark' ),
			self::ENROLLMENT_GRANT                       => __( 'Enrollment Grant', 'pastmark' ),
			self::ENROLLMENT_PENDING                     => __( 'Enrollment Pending', 'pastmark' ),
			self::ENROLLMENT_REMOVE                      => __( 'Enrollment Remove', 'pastmark' ),
			self::LESSON_COMPLETE                        => __( 'Lesson Complete', 'pastmark' ),
			self::QUIZ_COMPLETE                          => __( 'Quiz Complete', 'pastmark' ),
			self::COURSE_COMPLETE                        => __( 'Course Complete', 'pastmark' ),
		);

		return $labels;
	}
}
