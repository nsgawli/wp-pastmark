<?php // phpcs:ignore WordPress.Files.FileName.NotHyphenatedLowercase
namespace LogTrail\Admin;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ActivityLogsPage {

	/**
	 * Activity logs filters cookie name.
	 *
	 * @var string
	 */
	const FILTERS_COOKIE_NAME = 'logtrail_activity_log_filters';

	/**
	 * Initialize the class.
	 *
	 * @version 1.0.0
	 * @return void
	 */
	public static function init() {

		add_filter( 'admin_menu', array( __CLASS__, 'register_menu' ) );

		add_filter( 'admin_menu', array( __CLASS__, 'remove_menu' ), 999 );

		// Enqueue the scripts and styles for the admin page.
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_scripts' ) );
	}

	/**
	 * Add submenu for settings.
	 *
	 * @param array $submenus - submenu array.
	 * @return void
	 */
	public static function register_menu( $submenus ) {

		add_menu_page(
			esc_attr__( 'User Activity', 'logtrail' ),
			esc_attr__( 'User Activity', 'logtrail' ),
			'manage_options',
			'logtrail',
			array( __CLASS__, 'render_logs_page' ),
			self::get_menu_icon(),
			40
		);

		$submenus = array(
			array(
				'page_title' => esc_attr__( 'Activity Logs', 'logtrail' ),
				'menu_title' => esc_attr__( 'Activity Logs', 'logtrail' ),
				'capability' => 'manage_options',
				'menu_slug'  => 'logtrail',
				'callback'   => array( __CLASS__, 'render_logs_page' ),
				'position'   => 2,
			),
		);
		$submenus = apply_filters( 'logtrails_admin_submenus', $submenus );

		usort(
			$submenus,
			function ( $a, $b ) {
				return $a['position'] <=> $b['position'];
			}
		);

		// add submenus.
		foreach ( $submenus as $submenu ) {
			add_submenu_page(
				'logtrail',
				$submenu['page_title'],
				$submenu['menu_title'],
				$submenu['capability'],
				$submenu['menu_slug'],
				$submenu['callback']
			);
		}
	}

	/**
	 * Remove submenu for settings.
	 *
	 * @return void
	 */
	public static function remove_menu() {
		remove_submenu_page( 'logtrail', 'logtrail' );
	}

	/**
	 * Build the admin menu icon.
	 *
	 * An "LT" (LogTrail) lettermark SVG as a base64 data URI, per the WP
	 * convention for custom menu icons. WP renders this as a plain
	 * background-image with no opacity/color adjustment of its own
	 * (that treatment only applies to dashicon font glyphs), so the
	 * fill is hardcoded here to `#a7aaad` — the same idle grey WP
	 * itself uses for dashicon menu items — so it blends in with the
	 * rest of the admin menu rather than standing out as a solid
	 * black or white square against the dark sidebar.
	 *
	 * @return string
	 */
	private static function get_menu_icon(): string {

		$svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">'
			. '<text x="10" y="15.5" text-anchor="middle" font-family="Arial, Helvetica, sans-serif" '
			. 'font-size="13.5" font-weight="700" letter-spacing="-0.6" fill="#a7aaad">LT</text>'
			. '</svg>';

		return 'data:image/svg+xml;base64,' . base64_encode( $svg ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
	}

	/**
	 * Render the activity logs page.
	 *
	 * This method is called when the activity logs page is accessed and is responsible
	 * for rendering the content of the activity logs page.
	 *
	 * @return void
	 */
	public static function render_logs_page() {
		echo '<div id="logtrail-activity-logs"></div>';
	}

	/**
	 * Enqueues the scripts and styles for the admin page.
	 *
	 * This method is called during the admin_enqueue_scripts action and is responsible
	 * for enqueuing the necessary scripts and styles for the admin page.
	 *
	 * @param string $hook The current admin page hook.
	 * @return void
	 */
	public static function enqueue_scripts( $hook ) {

		if ( ! preg_match( '/^toplevel_page_logtrail/', $hook ) ) {
			return;
		}

		// add body class for the admin page.
		add_filter(
			'admin_body_class',
			function ( $classes ) {
				$classes .= ' toplevel-logtrail-page';
				return $classes;
			}
		);

		// Load asset file.
		$assets = require WPLT_ABSPATH . 'build/activity-logs/index.asset.php';

		// Enqueue page styles.
		wp_enqueue_style(
			'logtrail-activity-logs',
			WPLT_PLUGIN_URL . 'build/activity-logs/index' . ( is_rtl() ? '-rtl.css' : '.css' ),
			array(),
			$assets['version']
		);

		// Enqueue page script.
		wp_enqueue_script(
			'logtrail-activity-logs',
			WPLT_PLUGIN_URL . 'build/activity-logs/index.js',
			$assets['dependencies'],
			$assets['version'],
			true
		);

		$script_data = array(
			'initialAdvancedFilters' => self::get_initial_advanced_filters(),
		);

		wp_add_inline_script(
			'logtrail-activity-logs',
			'window.logtrailActivityLogsConfig = ' . wp_json_encode( $script_data ) . ';',
			'before'
		);

		// script translations.
		wp_set_script_translations( 'logtrail-activity-logs', 'logtrail' );
	}

	/**
	 * Get sanitized initial advanced filters from cookie.
	 *
	 * @return array<string, mixed>
	 */
	private static function get_initial_advanced_filters() {

		if ( ! isset( $_COOKIE[ self::FILTERS_COOKIE_NAME ] ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			return array();
		}

		$raw_cookie = wp_unslash( $_COOKIE[ self::FILTERS_COOKIE_NAME ] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$decoded    = urldecode( $raw_cookie );
		$parsed     = json_decode( $decoded, true );

		if ( ! is_array( $parsed ) ) {
			return array();
		}

		$allowed_keys = array(
			'user_ids',
			'event',
			'severity',
			'ids',
			'date_range',
			'date_from',
			'date_to',
		);

		$array_keys = array(
			'user_ids',
			'event',
			'severity',
			'ids',
		);

		$filters = array();

		foreach ( $allowed_keys as $key ) {
			if ( ! isset( $parsed[ $key ] ) ) {
				continue;
			}

			if ( in_array( $key, $array_keys, true ) ) {
				if ( ! is_array( $parsed[ $key ] ) ) {
					continue;
				}

				$items = array();

				foreach ( $parsed[ $key ] as $item ) {
					if ( ! is_scalar( $item ) ) {
						continue;
					}

					$value = trim( sanitize_text_field( (string) $item ) );

					if ( '' === $value ) {
						continue;
					}

					$items[] = $value;
				}

				if ( ! empty( $items ) ) {
					$filters[ $key ] = array_values( array_unique( $items ) );
				}

				continue;
			}

			if ( ! is_scalar( $parsed[ $key ] ) ) {
				continue;
			}

			$value = trim( sanitize_text_field( (string) $parsed[ $key ] ) );

			if ( '' === $value ) {
				continue;
			}

			$filters[ $key ] = $value;
		}

		return $filters;
	}
}
