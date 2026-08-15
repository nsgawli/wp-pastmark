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
			self::USER           => __( 'Users', 'pastmark' ),
			self::AUTHENTICATION => __( 'Authentication', 'pastmark' ),
			self::CONTENT        => __( 'Content', 'pastmark' ),
			self::COMMENT        => __( 'Comments', 'pastmark' ),
			self::MEDIA          => __( 'Media', 'pastmark' ),
			self::PLUGIN         => __( 'Plugins', 'pastmark' ),
			self::THEME          => __( 'Themes', 'pastmark' ),
			self::SETTINGS       => __( 'Settings', 'pastmark' ),
			self::WIDGET         => __( 'Widgets', 'pastmark' ),
			self::MENU           => __( 'Menus', 'pastmark' ),
			self::WOOCOMMERCE    => __( 'WooCommerce', 'pastmark' ),
		);

		return $labels;
	}
}
