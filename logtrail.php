<?php // phpcs:ignore
/**
 * Plugin Name: LogTrail - User Activity Logs
 * Description: Easy & Powerful User Activity Log Plugin for WordPress. Track user activity, changes, and events in your WordPress site with ease.
 * Version: 1.0.0
 * Author: nsgawli
 * Author URI: https://wordpress.org/plugins/debug-log-tool/
 * License: GPL v3 or later
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain: logtrail
 *
 * @package logtrail
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class LogTrail {

	/**
	 * Plugin version
	 *
	 * @var string
	 */
	public static $version = '1.0.0';

	/**
	 * The single instance of the class.
	 *
	 * @var LogTrail
	 */
	public static function init() {

		add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), array( __CLASS__, 'add_plugin_links' ) );
		add_action( 'plugins_loaded', array( __CLASS__, 'includes' ) );
	}

	/**
	 * Include the plugin files.
	 */
	public static function includes() {

		// define constants.
		self::define_constants();

		// load plugin files.
		require_once WPLT_ABSPATH . 'vendor/autoload.php';

		LogTrail\Init::run();
	}

	/**
	 * Defines global constants that can be availabel anywhere in WordPress
	 *
	 * @return void
	 */
	public static function define_constants() {

		self::define( 'WPLT_PLUGIN_FILE', __FILE__ );
		self::define( 'WPLT_ABSPATH', __DIR__ . '/' );
		self::define( 'WPLT_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
		self::define( 'WPLT_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
		self::define( 'WPLT_VERSION', self::$version );

		$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$is_rest_request = ( false !== strpos( $request_uri, '/' . rest_get_url_prefix() . '/' ) )
			|| isset( $_GET['rest_route'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		self::define( 'WPLT_REST_REQUEST', $is_rest_request );

		// load admin user interface.
		if ( is_admin() && ! ( wp_doing_ajax() || WPLT_REST_REQUEST ) ) {
			self::define( 'WPLT_ADMIN_INTERFACE', true );
		} else {
			self::define( 'WPLT_ADMIN_INTERFACE', false );
		}

		// load frontend user interface.
		if ( ! ( is_admin() || defined( 'DOING_CRON' ) || WPLT_REST_REQUEST ) ) {
			self::define( 'WPLT_FRONTEND_INTERFACE', true );
		} else {
			self::define( 'WPLT_FRONTEND_INTERFACE', false );
		}
	}

	/**
	 * Define constants
	 *
	 * @param string $name - name of global constant.
	 * @param string $value - value of constant.
	 * @return void
	 */
	private static function define( $name, $value ) {
		if ( ! defined( $name ) ) {
			define( $name, $value ); //phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.VariableConstantNameFound
		}
	}

	/**
	 * Add plugin action links
	 *
	 * @param array $links - array of plugin action links.
	 * @return array
	 */
	public static function add_plugin_links( $links ) {
		$custom_links = array(
			'<a href="' . admin_url( 'admin.php?page=logtrail' ) . '">' . __( 'Settings', 'logtrail' ) . '</a>',
		);
		return array_merge( $links, $custom_links );
	}
}

LogTrail::init();
