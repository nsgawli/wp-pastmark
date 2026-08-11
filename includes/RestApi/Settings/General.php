<?php // phpcs:ignore WordPress.Files.FileName.NotHyphenatedLowercase
namespace LogTrail\RestApi\Settings;

use LogTrail\Installation\Settings\General as InstallationGeneral;
use LogTrail\RestApi\BaseController;
use WP_REST_Request;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * General settings REST controller.
 */
class General extends BaseController {

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
			'/settings/general-settings',
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

		register_rest_route(
			$this->namespace,
			'settings/general-settings/defaults',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_default_settings' ),
				'permission_callback' => array( $this, 'permission_callback' ),
			)
		);
	}

	/**
	 * Get settings.
	 *
	 * @return \WP_REST_Response
	 */
	public function get_settings() {

		$settings = wp_parse_args(
			get_option( 'logtrail_general_settings', array() ),
			InstallationGeneral::get_default_settings()
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

		$log_details_view_mode = sanitize_text_field(
			$request->get_param( 'logDetailsViewMode' )
		);

		if ( ! in_array( $log_details_view_mode, array( 'drawer', 'single_page' ), true ) ) {
			$log_details_view_mode = 'drawer';
		}

		$logs_page_view_mode = sanitize_text_field(
			$request->get_param( 'logsPageViewMode' )
		);

		if ( ! in_array( $logs_page_view_mode, array( 'table', 'timeline' ), true ) ) {
			$logs_page_view_mode = 'table';
		}

		$settings = array(
			'dashboardWidget'      => (bool) $request->get_param( 'dashboardWidget' ),
			'eventTimestamp'       => sanitize_text_field(
				$request->get_param( 'eventTimestamp' )
			),
			'logDetailsViewMode'   => $log_details_view_mode,
			'logsPageViewMode'     => $logs_page_view_mode,
			'enableAutoDeleteLogs' => (bool) $request->get_param( 'enableAutoDeleteLogs' ),
			'autoDeleteTime'       => (int) $request->get_param( 'autoDeleteTime' ),
			'autoDeleteUnit'       => sanitize_text_field( $request->get_param( 'autoDeleteUnit' ) ),
		);

		update_option( 'logtrail_general_settings', $settings );

		return $this->success_response( $settings );
	}

	/**
	 * Get default settings.
	 *
	 * @return \WP_REST_Response
	 */
	public function get_default_settings() {

		return $this->success_response(
			InstallationGeneral::get_default_settings()
		);
	}
}
