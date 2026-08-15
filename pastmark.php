<?php // phpcs:ignore
/**
 * Plugin Name: Pastmark - Activity Logs for WordPress
 * Description: Easy & Powerful User Activity Log Plugin for WordPress. Track user activity, changes, and events in your WordPress site with ease.
 * Version: 1.0.0
 * Author: nsgawli
 * License: GPL v3 or later
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain: pastmark
 *
 * @package pastmark
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Pastmark {

	/**
	 * Plugin version
	 *
	 * @var string
	 */
	public static $version = '1.0.0';

	/**
	 * The single instance of the class.
	 *
	 * @var Pastmark
	 */
	public static function init() {

		add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), array( __CLASS__, 'add_plugin_links' ) );
		add_action( 'plugins_loaded', array( __CLASS__, 'includes' ) );
	}

	/**
	 * Plugin activation callback.
	 *
	 * Fires immediately when the plugin is (re)activated -- in the same
	 * request as activation itself, before `plugins_loaded` has ever run
	 * for this plugin, so `includes()` hasn't executed yet and none of the
	 * activity loggers (including PluginActivityLogger, which normally
	 * records `activated_plugin`) are registered yet. Boots just enough
	 * (constants + autoloader) to run first-time setup and record the
	 * plugin's own activation, so the activity log isn't empty until some
	 * other event happens to populate it later.
	 *
	 * @return void
	 */
	public static function activate() {

		self::define_constants();

		require_once PASTMARK_ABSPATH . 'vendor/autoload.php';

		Pastmark\Installation\Autoloader::activate();
	}

	/**
	 * Include the plugin files.
	 */
	public static function includes() {

		// define constants.
		self::define_constants();

		// load plugin files.
		require_once PASTMARK_ABSPATH . 'vendor/autoload.php';

		Pastmark\Init::run();
	}

	/**
	 * Defines global constants that can be availabel anywhere in WordPress
	 *
	 * @return void
	 */
	public static function define_constants() {

		self::define( 'PASTMARK_PLUGIN_FILE', __FILE__ );
		self::define( 'PASTMARK_ABSPATH', __DIR__ . '/' );
		self::define( 'PASTMARK_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
		self::define( 'PASTMARK_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
		self::define( 'PASTMARK_VERSION', self::$version );

		$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$is_rest_request = ( false !== strpos( $request_uri, '/' . rest_get_url_prefix() . '/' ) )
			|| isset( $_GET['rest_route'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		self::define( 'PASTMARK_REST_REQUEST', $is_rest_request );

		// load admin user interface.
		if ( is_admin() && ! ( wp_doing_ajax() || PASTMARK_REST_REQUEST ) ) {
			self::define( 'PASTMARK_ADMIN_INTERFACE', true );
		} else {
			self::define( 'PASTMARK_ADMIN_INTERFACE', false );
		}

		// load frontend user interface.
		if ( ! ( is_admin() || defined( 'DOING_CRON' ) || PASTMARK_REST_REQUEST ) ) {
			self::define( 'PASTMARK_FRONTEND_INTERFACE', true );
		} else {
			self::define( 'PASTMARK_FRONTEND_INTERFACE', false );
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
			'<a href="' . admin_url( 'admin.php?page=pastmark' ) . '">' . __( 'Settings', 'pastmark' ) . '</a>',
		);
		return array_merge( $links, $custom_links );
	}
}

register_activation_hook( __FILE__, array( 'Pastmark', 'activate' ) );

Pastmark::init();
