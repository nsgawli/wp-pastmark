<?php // phpcs:ignore WordPress.Files.FileName.NotHyphenatedLowercase
namespace LogTrail\Admin;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Admin {

	/**
	 * Initialize the class.
	 *
	 * @version 1.0.0
	 * @return void
	 */
	public static function init() {

		// Enqueue the scripts and styles for the admin page.
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_scripts' ) );
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

		// add body class for the admin page.
		add_filter(
			'admin_body_class',
			function ( $classes ) {
				$classes .= ' toplevel-logtrail-page';
				return $classes;
			}
		);

		// Load asset file.
		$assets = require WPLT_ABSPATH . 'build/common/index.asset.php';

		// Enqueue page styles.
		wp_enqueue_style(
			'logtrail-common-scripts',
			WPLT_PLUGIN_URL . 'build/common/index' . ( is_rtl() ? '-rtl.css' : '.css' ),
			array(),
			$assets['version']
		);

		// Enqueue page script.
		wp_enqueue_script(
			'logtrail-common-scripts',
			WPLT_PLUGIN_URL . 'build/common/index.js',
			$assets['dependencies'],
			$assets['version'],
			true
		);

		// script translations.
		wp_set_script_translations( 'logtrail-common-scripts', 'logtrail' );
	}
}
