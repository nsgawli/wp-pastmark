<?php // phpcs:ignore WordPress.Files.FileName.NotHyphenatedLowercase

namespace LogTrail\Constants;

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
			self::USER           => __( 'Users', 'logtrail' ),
			self::AUTHENTICATION => __( 'Authentication', 'logtrail' ),
			self::CONTENT        => __( 'Content', 'logtrail' ),
			self::COMMENT        => __( 'Comments', 'logtrail' ),
			self::MEDIA          => __( 'Media', 'logtrail' ),
			self::PLUGIN         => __( 'Plugins', 'logtrail' ),
			self::THEME          => __( 'Themes', 'logtrail' ),
			self::SETTINGS       => __( 'Settings', 'logtrail' ),
			self::WIDGET         => __( 'Widgets', 'logtrail' ),
			self::MENU           => __( 'Menus', 'logtrail' ),
			self::WOOCOMMERCE    => __( 'WooCommerce', 'logtrail' ),
		);

		return $labels;
	}
}
