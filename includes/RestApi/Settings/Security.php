<?php // phpcs:ignore WordPress.Files.FileName.NotHyphenatedLowercase
namespace Pastmark\RestApi\Settings;

use Pastmark\Installation\Settings\Security as InstallationSecurity;
use Pastmark\RestApi\BaseController;
use WP_REST_Request;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Security & Privacy settings REST controller.
 *
 * Backs the Settings screen's "Security & Privacy" tab (PM-138): IP
 * anonymization (`IpAnonymizer`) and failed-login throttle tuning
 * (`Utils\LoginThrottle`, which reads this same option directly).
 */
class Security extends BaseController {

	/**
	 * Minimum/maximum bounds enforced on `loginThrottleWindow`, in seconds.
	 */
	const MIN_THROTTLE_WINDOW = 60;
	const MAX_THROTTLE_WINDOW = 3600;

	/**
	 * Minimum/maximum bounds enforced on `loginThrottleMax`.
	 */
	const MIN_THROTTLE_MAX = 1;
	const MAX_THROTTLE_MAX = 100;

	/**
	 * Initialize hooks.
	 *
	 * @return void
	 */
	public static function init() {

		$instance = new self();

		add_action(
			'rest_api_init',
			array( $instance, 'register_routes' )
		);
	}

	/**
	 * Register REST routes.
	 *
	 * @return void
	 */
	public function register_routes() {

		register_rest_route(
			$this->namespace,
			'/settings/security',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'get_settings' ),
					'permission_callback' => array( $this, 'permission_callback' ),
				),
				array(
					'methods'             => 'PUT',
					'callback'            => array( $this, 'update_settings' ),
					'permission_callback' => array( $this, 'permission_callback' ),
				),
			)
		);
	}

	/**
	 * Get settings.
	 *
	 * @return \WP_REST_Response
	 */
	public function get_settings() {

		$settings = get_option(
			'pastmark_security_settings',
			InstallationSecurity::get_default_settings()
		);

		$settings = wp_parse_args(
			$settings,
			InstallationSecurity::get_default_settings()
		);

		return $this->success_response( $settings );
	}

	/**
	 * Update settings.
	 *
	 * @param WP_REST_Request $request Request object.
	 *
	 * @return \WP_REST_Response
	 */
	public function update_settings( WP_REST_Request $request ) {

		$defaults = InstallationSecurity::get_default_settings();

		$window = $request->has_param( 'loginThrottleWindow' )
			? (int) $request->get_param( 'loginThrottleWindow' )
			: $defaults['loginThrottleWindow'];

		$max_attempts = $request->has_param( 'loginThrottleMax' )
			? (int) $request->get_param( 'loginThrottleMax' )
			: $defaults['loginThrottleMax'];

		$settings = array(
			'anonymizeIp'         => (bool) $request->get_param( 'anonymizeIp' ),
			'loginThrottleWindow' => min( max( $window, self::MIN_THROTTLE_WINDOW ), self::MAX_THROTTLE_WINDOW ),
			'loginThrottleMax'    => min( max( $max_attempts, self::MIN_THROTTLE_MAX ), self::MAX_THROTTLE_MAX ),
		);

		update_option( 'pastmark_security_settings', $settings );

		return $this->success_response( $settings );
	}
}
