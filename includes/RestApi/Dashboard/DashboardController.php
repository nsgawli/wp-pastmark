<?php // phpcs:ignore WordPress.Files.FileName.NotHyphenatedLowercase

namespace Pastmark\RestApi\Dashboard;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Pastmark\Dashboard\DashboardService;
use Pastmark\RestApi\BaseController;
use WP_REST_Request;

/**
 * Dashboard REST Controller.
 */
class DashboardController extends BaseController {

	/**
	 * Initialize routes.
	 *
	 * @return void
	 */
	public static function init() {

		$controller = new self();

		add_action(
			'rest_api_init',
			array(
				$controller,
				'register_routes',
			)
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
			'/dashboard',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array(
						$this,
						'get_dashboard',
					),
					'permission_callback' => array(
						$this,
						'permission_callback',
					),
				),
			)
		);
	}

	/**
	 * Get dashboard.
	 *
	 * @param WP_REST_Request $request Request.
	 *
	 * @return \WP_REST_Response
	 */
	public function get_dashboard(
		WP_REST_Request $request
	) {

		$range = sanitize_text_field(
			$request->get_param( 'range' )
		);

		if ( empty( $range ) ) {
			$range = 'today';
		}

		$service = new DashboardService();

		$data = $service->get_dashboard(
			$range
		);

		return $this->success_response(
			$data
		);
	}
}
