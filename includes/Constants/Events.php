<?php // phpcs:ignore WordPress.Files.FileName.NotHyphenatedLowercase

namespace Pastmark\Constants;

defined( 'ABSPATH' ) || exit;

/**
 * Event constants.
 */
class Events {

	/**
	 * User events.
	 */
	public const USER = 'user';

	/**
	 * Authentication events.
	 */
	public const AUTHENTICATION = 'authentication';

	/**
	 * Content events.
	 */
	public const CONTENT = 'content';

	/**
	 * Comment events.
	 */
	public const COMMENT = 'comment';

	/**
	 * Media events.
	 */
	public const MEDIA = 'media';

	/**
	 * Plugin events.
	 */
	public const PLUGIN = 'plugin';

	/**
	 * Theme events.
	 */
	public const THEME = 'theme';

	/**
	 * Settings events.
	 */
	public const SETTINGS = 'settings';

	/**
	 * Widget events.
	 */
	public const WIDGET = 'widget';

	/**
	 * Menu events.
	 */
	public const MENU = 'menu';

	/**
	 * WooCommerce events.
	 */
	public const WOOCOMMERCE = 'woocommerce';

	/**
	 * ACF (Advanced Custom Fields) events (PM-154). Unlike `WOOCOMMERCE`
	 * above, this group is never hardcoded into `EventRegistry::get_events()`
	 * — `ACFIntegration`'s logger classes declare it themselves via the
	 * `pastmark_registered_events` filter, per the convention settled in
	 * `docs/INTEGRATION-FRAMEWORK-ARCHITECTURE.md` decision 4. This constant
	 * exists purely so `ACFIntegration`'s own loggers and its
	 * `pastmark_registered_events` callback agree on the same string
	 * without repeating a literal in two places.
	 */
	public const ACF = 'acf';

	/**
	 * WPForms events (PM-155). Same non-hardcoded pattern as `ACF` above -
	 * declared via the `pastmark_registered_events` filter from
	 * `WPForms\FormActivityLogger`, never added to
	 * `EventRegistry::get_events()` directly.
	 */
	public const WPFORMS = 'wpforms';

	/**
	 * Gravity Forms events (PM-156). Same non-hardcoded pattern as `ACF`/
	 * `WPFORMS` above - declared via the `pastmark_registered_events`
	 * filter from `GravityForms\FormActivityLogger`, never added to
	 * `EventRegistry::get_events()` directly.
	 */
	public const GRAVITYFORMS = 'gravityforms';

	/**
	 * Yoast SEO events (PM-166, Sprint 10). Same non-hardcoded pattern as
	 * the other integrations above - declared via the
	 * `pastmark_registered_events` filter from
	 * `YoastSeo\SettingsActivityLogger`, never added to
	 * `EventRegistry::get_events()` directly. Hyphenated (unlike `acf`/
	 * `wpforms`/`gravityforms`) per this ticket's own settled `source`
	 * convention, `yoast-seo` - kept identical across the event-type key,
	 * the `source` field, and both loggers' `$integration` property.
	 */
	public const YOAST_SEO = 'yoast-seo';

	/**
	 * RankMath events (PM-167, Sprint 10). Same non-hardcoded pattern as
	 * `YOAST_SEO` above - declared via the `pastmark_registered_events`
	 * filter from `RankMath\SettingsActivityLogger`, never added to
	 * `EventRegistry::get_events()` directly. Hyphenated, matching
	 * `yoast-seo`'s own convention - kept identical across the event-type
	 * key, the `source` field, and both loggers' `$integration` property.
	 */
	public const RANK_MATH = 'rank-math';

	/**
	 * Redirection plugin events (PM-168, Sprint 10). Same non-hardcoded
	 * pattern as `YOAST_SEO`/`RANK_MATH` above - declared via the
	 * `pastmark_registered_events` filter from
	 * `Redirection\RedirectActivityLogger`, never added to
	 * `EventRegistry::get_events()` directly.
	 */
	public const REDIRECTION = 'redirection';

	/**
	 * TablePress events (PM-170, Sprint 11). Same non-hardcoded pattern as
	 * the other integrations above - declared via the
	 * `pastmark_registered_events` filter from
	 * `TablePress\TableActivityLogger`, never added to
	 * `EventRegistry::get_events()` directly.
	 */
	public const TABLEPRESS = 'tablepress';

	/**
	 * BbPress events (PM-171, Sprint 11). Same non-hardcoded pattern as
	 * the other integrations above - declared via the
	 * `pastmark_registered_events` filter from
	 * `BbPress\ForumActivityLogger` (the group is shared across all four
	 * of this integration's loggers, the same way ACF's `acf` group is
	 * shared across `FieldGroupActivityLogger`/`FieldActivityLogger`),
	 * never added to `EventRegistry::get_events()` directly.
	 */
	public const BBPRESS = 'bbpress';

	/**
	 * WP 2FA events (PM-172, Sprint 11). Same non-hardcoded pattern as
	 * the other integrations above - declared via the
	 * `pastmark_registered_events` filter from
	 * `WP2FA\TwoFactorActivityLogger`, never added to
	 * `EventRegistry::get_events()` directly. Hyphenated, matching
	 * `yoast-seo`/`rank-math`'s own convention.
	 */
	public const WP_2FA = 'wp-2fa';

	/**
	 * Ultimate Member events (PM-174, Sprint 12). Same non-hardcoded
	 * pattern as the other integrations above - declared via the
	 * `pastmark_registered_events` filter from
	 * `UltimateMember\RegistrationActivityLogger` (the group is shared
	 * across all three of this integration's loggers, the same way
	 * bbPress' `bbpress` group is shared across its four), never added to
	 * `EventRegistry::get_events()` directly. Hyphenated, matching
	 * `wp-2fa`'s own convention.
	 */
	public const ULTIMATE_MEMBER = 'ultimate-member';

	/**
	 * Wordfence events (PM-175, Sprint 12). Same non-hardcoded pattern as
	 * the other integrations above - declared via the
	 * `pastmark_registered_events` filter from
	 * `Wordfence\LoginSecurityActivityLogger` (the group is shared across
	 * all three of this integration's loggers, the same way `ultimate-
	 * member`'s group is shared across its three), never added to
	 * `EventRegistry::get_events()` directly.
	 */
	public const WORDFENCE = 'wordfence';

	/**
	 * Tutor LMS events (PM-176, Sprint 12). Same non-hardcoded pattern as
	 * the other integrations above - declared via the
	 * `pastmark_registered_events` filter from
	 * `TutorLms\CourseContentActivityLogger` (the group is shared across
	 * both of this integration's loggers), never added to
	 * `EventRegistry::get_events()` directly. Hyphenated, matching
	 * `ultimate-member`'s own convention.
	 */
	public const TUTOR_LMS = 'tutor-lms';

	/**
	 * Resolve event label from known keys with fallback.
	 *
	 * @param string $event_key Event key.
	 * @return string
	 */
	public static function resolve_label( string $event_key ): string {

		$labels = self::get_labels();

		if ( isset( $labels[ $event_key ] ) ) {
			return $labels[ $event_key ];
		}

		return ucwords(
			str_replace(
				'_',
				' ',
				$event_key
			)
		);
	}

	/**
	 * Get memoized event labels map.
	 *
	 * @return array
	 */
	private static function get_labels(): array {

		static $labels = null;

		if ( null !== $labels ) {
			return $labels;
		}

		$labels = array(
			self::USER            => __( 'Users', 'pastmark' ),
			self::AUTHENTICATION  => __( 'Authentication', 'pastmark' ),
			self::CONTENT         => __( 'Content', 'pastmark' ),
			self::COMMENT         => __( 'Comments', 'pastmark' ),
			self::MEDIA           => __( 'Media', 'pastmark' ),
			self::PLUGIN          => __( 'Plugins', 'pastmark' ),
			self::THEME           => __( 'Themes', 'pastmark' ),
			self::SETTINGS        => __( 'Settings', 'pastmark' ),
			self::WIDGET          => __( 'Widgets', 'pastmark' ),
			self::MENU            => __( 'Menus', 'pastmark' ),
			self::WOOCOMMERCE     => __( 'WooCommerce', 'pastmark' ),
			self::ACF             => __( 'ACF', 'pastmark' ),
			self::WPFORMS         => __( 'WPForms', 'pastmark' ),
			self::GRAVITYFORMS    => __( 'Gravity Forms', 'pastmark' ),
			self::YOAST_SEO       => __( 'Yoast SEO', 'pastmark' ),
			self::RANK_MATH       => __( 'RankMath', 'pastmark' ),
			self::REDIRECTION     => __( 'Redirection', 'pastmark' ),
			self::TABLEPRESS      => __( 'TablePress', 'pastmark' ),
			self::BBPRESS         => __( 'bbPress', 'pastmark' ),
			self::WP_2FA          => __( 'WP 2FA', 'pastmark' ),
			self::ULTIMATE_MEMBER => __( 'Ultimate Member', 'pastmark' ),
			self::WORDFENCE       => __( 'Wordfence', 'pastmark' ),
			self::TUTOR_LMS       => __( 'Tutor LMS', 'pastmark' ),
		);

		return $labels;
	}
}
