<?php // phpcs:ignore WordPress.Files.FileName.NotHyphenatedLowercase

namespace LogTrail\RestApi\Settings;

use LogTrail\EventSettings\EventRegistry;
use LogTrail\EventSettings\EventSettings;
use LogTrail\RestApi\BaseController;
use LogTrail\EventSettings\EventPresets;
use WP_REST_Request;

defined( 'ABSPATH' ) || exit;

/**
 * Event settings REST controller.
 */
class Events extends BaseController {

	/**
	 * Init controller.
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
	 * Register routes.
	 *
	 * @return void
	 */
	public function register_routes() {

		register_rest_route(
			$this->namespace,
			'/settings/events',
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

		return $this->success_response(
			array(
				'events'   => EventRegistry::get_events(),
				'settings' => EventSettings::get_settings(),
				'logLevel' => EventSettings::get_log_level(),
				'presets'  => EventPresets::get_presets(),
			)
		);
	}

	/**
	 * Update settings.
	 *
	 * @param WP_REST_Request $request Request.
	 *
	 * @return \WP_REST_Response
	 */
	public function update_settings( WP_REST_Request $request ) {

		$settings = $request->get_param( 'settings' );
		$log_level = $request->get_param( 'logLevel' );

		if ( ! is_array( $settings ) ) {

			return $this->error_response(
				'invalid_settings',
				'Invalid settings payload.'
			);
		}

		EventSettings::update_settings( $settings );

		if ( is_string( $log_level ) ) {
			EventSettings::update_log_level( $log_level );
		}

		return $this->success_response(
			array(
				'settings' => EventSettings::get_settings(),
				'logLevel' => EventSettings::get_log_level(),
			)
		);
	}
}
