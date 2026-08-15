<?php // phpcs:ignore WordPress.Files.FileName.NotHyphenatedLowercase
namespace Pastmark\Admin;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class EventsPage {

	/**
	 * Initialize the class.
	 *
	 * @version 1.0.0
	 * @return void
	 */
	public static function init() {

		add_filter( 'pastmark_admin_submenus', array( __CLASS__, 'add_submenu' ) );

		// Enqueue the scripts and styles for the admin page.
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_scripts' ) );
	}

	/**
	 * Add submenu for settings.
	 *
	 * @param array $submenus - submenu array.
	 * @return array
	 */
	public static function add_submenu( $submenus ) {

		$submenus[] = array(
			'page_title' => esc_attr__( 'Events', 'pastmark' ),
			'menu_title' => esc_attr__( 'Events', 'pastmark' ),
			'capability' => 'manage_options',
			'menu_slug'  => 'events',
			'callback'   => array( __CLASS__, 'render_events_page' ),
			'position'   => 3,
		);

		return $submenus;
	}

	/**
	 * Render the events page.
	 *
	 * This method is called when the events page is accessed and is responsible
	 * for rendering the content of the events page.
	 *
	 * @return void
	 */
	public static function render_events_page() {
		echo '<div id="pastmark-events-settings"></div>';
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

		if ( ! preg_match( '/^user-activity_page_events/', $hook ) ) {
			return;
		}

		// add body class for the admin page.
		add_filter(
			'admin_body_class',
			function ( $classes ) {
				$classes .= ' toplevel-pastmark-page';
				return $classes;
			}
		);

		// Load asset file.
		$assets = require PASTMARK_ABSPATH . 'build/events-settings/index.asset.php';

		// Enqueue page styles.
		wp_enqueue_style(
			'pastmark-events-settings',
			PASTMARK_PLUGIN_URL . 'build/events-settings/index' . ( is_rtl() ? '-rtl.css' : '.css' ),
			array(),
			$assets['version']
		);

		// Enqueue page script.
		wp_enqueue_script(
			'pastmark-events-settings',
			PASTMARK_PLUGIN_URL . 'build/events-settings/index.js',
			$assets['dependencies'],
			$assets['version'],
			true
		);

		// script translations.
		wp_set_script_translations( 'pastmark-events-settings', 'pastmark' );
	}
}
