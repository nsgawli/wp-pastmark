<?php // phpcs:ignore WordPress.Files.FileName.NotHyphenatedLowercase
namespace Pastmark\Admin;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DashboardPage {

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
	 * Add submenu for dashboard.
	 *
	 * @param array $submenus - submenu array.
	 * @return array
	 */
	public static function add_submenu( $submenus ) {

		$submenus[] = array(
			'page_title' => esc_attr__( 'Dashboard', 'pastmark' ),
			'menu_title' => esc_attr__( 'Dashboard', 'pastmark' ),
			'capability' => 'manage_options',
			'menu_slug'  => 'dashboard',
			'callback'   => array( __CLASS__, 'render_dashboard_page' ),
			'position'   => 1,
		);

		return $submenus;
	}

	/**
	 * Render the dashboard page.
	 *
	 * This method is called when the dashboard page is accessed and is responsible
	 * for rendering the content of the dashboard page.
	 *
	 * @return void
	 */
	public static function render_dashboard_page() {
		echo '<div id="pastmark-dashboard"></div>';
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

		if ( ! preg_match( '/^user-activity_page_dashboard/', $hook ) ) {
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
		$assets = require PASTMARK_ABSPATH . 'build/dashboard/index.asset.php';

		// Enqueue page styles.
		wp_enqueue_style(
			'pastmark-dashboard',
			PASTMARK_PLUGIN_URL . 'build/dashboard/index' . ( is_rtl() ? '-rtl.css' : '.css' ),
			array(),
			$assets['version']
		);

		// Enqueue page script.
		wp_enqueue_script(
			'pastmark-dashboard',
			PASTMARK_PLUGIN_URL . 'build/dashboard/index.js',
			$assets['dependencies'],
			$assets['version'],
			true
		);

		// script translations.
		wp_set_script_translations( 'pastmark-dashboard', 'pastmark' );
	}
}
