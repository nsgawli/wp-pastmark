<?php // phpcs:ignore WordPress.Files.FileName.NotHyphenatedLowercase
namespace Pastmark\RestApi\Settings;

use Pastmark\Installation\Settings\DataManagement as InstallationDataManagement;
use Pastmark\Models\Pastmark_Logs;
use Pastmark\RestApi\BaseController;
use WP_REST_Request;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Data Management settings REST controller.
 */
class DataManagement extends BaseController {

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
			'/settings/data-management',
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
			'/settings/data-management/delete-old-data',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'delete_old_data' ),
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

		$settings = get_option(
			'pastmark_data_management_settings',
			InstallationDataManagement::get_default_settings()
		);

		$settings = wp_parse_args(
			$settings,
			InstallationDataManagement::get_default_settings()
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

		$settings = array(
			'removeDataOnUninstall' => (bool) $request->get_param( 'removeDataOnUninstall' ),
		);

		update_option( 'pastmark_data_management_settings', $settings );

		return $this->success_response( $settings );
	}

	/**
	 * Delete old data instantly.
	 *
	 * @return \WP_REST_Response
	 */
	public function delete_old_data() {

		$logs_model = new Pastmark_Logs();
		$deleted    = $logs_model->delete_all_logs();

		return $this->success_response(
			array(
				'deleted' => $deleted,
			)
		);
	}
}
