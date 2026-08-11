<?php // phpcs:ignore WordPress.Files.FileName.NotHyphenatedLowercase
namespace LogTrail\RestApi\Logs;

use LogTrail\Constants\Actions;
use LogTrail\Constants\Events;
use LogTrail\Constants\Severity;
use LogTrail\Models\LogTrail_Logs;
use LogTrail\RestApi\BaseController;
use LogTrail\Utils\Helpers;
use WP_REST_Request;
use WP_REST_Server;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Logs REST controller.
 */
class Logs extends BaseController {


	/**
	 * Logs model.
	 *
	 * @var LogTrail_Logs
	 */
	protected $logs_model;

	/**
	 * Constructor.
	 */
	public function __construct() {

		$this->logs_model = new LogTrail_Logs();
	}

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
	 * Register routes.
	 *
	 * @return void
	 */
	public function register_routes() {

		register_rest_route(
			$this->namespace,
			'/logs',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_logs' ),
					'permission_callback' => array( $this, 'permission_callback' ),
					'args'                => $this->get_collection_params(),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/logs/(?P<id>\d+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_log_details' ),
					'permission_callback' => array( $this, 'permission_callback' ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/logs/stats',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_stats' ),
					'permission_callback' => array( $this, 'permission_callback' ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/logs/filter-options',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_filter_options' ),
					'permission_callback' => array( $this, 'permission_callback' ),
				),
			)
		);
	}

	/**
	 * Get logs collection params.
	 *
	 * @return array
	 */
	protected function get_collection_params() {

		return array(
			'page'       => array(
				'default'           => 1,
				'sanitize_callback' => 'absint',
			),

			'per_page'   => array(
				'default'           => 20,
				'sanitize_callback' => 'absint',
			),

			'search'     => array(
				'sanitize_callback' => 'sanitize_text_field',
			),

			'severity'   => array(
				'sanitize_callback' => 'sanitize_text_field',
			),

			'event'      => array(
				'sanitize_callback' => 'sanitize_text_field',
			),

			'user_ids'   => array(
				'sanitize_callback' => 'sanitize_text_field',
			),

			'ids'        => array(
				'sanitize_callback' => 'sanitize_text_field',
			),

			'date_range' => array(
				'default'           => 'all',
				'sanitize_callback' => 'sanitize_text_field',
			),

			'date_from'  => array(
				'sanitize_callback' => 'sanitize_text_field',
			),

			'date_to'    => array(
				'sanitize_callback' => 'sanitize_text_field',
			),

			'ip_address' => array(
				'sanitize_callback' => 'sanitize_text_field',
			),

			'orderby'    => array(
				'default'           => 'id',
				'sanitize_callback' => 'sanitize_text_field',
			),

			'order'      => array(
				'default'           => 'DESC',
				'sanitize_callback' => 'sanitize_text_field',
			),
		);
	}

	/**
	 * Get logs.
	 *
	 * @param WP_REST_Request $request Request object.
	 *
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function get_logs( WP_REST_Request $request ) {

		$page     = (int) $request->get_param( 'page' );
		$per_page = (int) $request->get_param( 'per_page' );

		if ( $page < 1 ) {
			$page = 1;
		}

		if ( $per_page < 1 ) {
			$per_page = 20;
		}

		$offset = ( $page - 1 ) * $per_page;

		$date_range = $this->resolve_date_range(
			$request->get_param( 'date_range' ),
			$request->get_param( 'date_from' ),
			$request->get_param( 'date_to' )
		);

		$args = array(
			'number'     => $per_page,
			'offset'     => $offset,
			'search'     => $request->get_param( 'search' ),
			'severity'   => $this->parse_csv_text( $request->get_param( 'severity' ) ),
			'event'      => $this->parse_csv_text( $request->get_param( 'event' ) ),
			'user_ids'   => $this->parse_csv_int( $request->get_param( 'user_ids' ) ),
			'ids'        => $this->parse_csv_int( $request->get_param( 'ids' ) ),
			'date_from'  => $date_range['from'],
			'date_to'    => $date_range['to'],
			'ip_address' => $request->get_param( 'ip_address' ),
			'order'      => $request->get_param( 'order' ),
			'orderby'    => $request->get_param( 'orderby' ),
		);

		$items = $this->logs_model->get_logs( $args );

		$total = $this->logs_model->count_logs( $args );

		$formatted_items = array();

		if ( ! empty( $items ) ) {

			foreach ( $items as $item ) {

				$user = get_user_by( 'id', $item->user_id );

				$formatted_items[] = array(
					'id'             => (int) $item->id,
					'date'           => Helpers::format_timestamp_for_display( $item->timestamp ),
					'timestamp'      => $item->timestamp,
					'user'           => $user ? ( $user->display_name ? $user->display_name : $user->user_login ) : 'System',
					'event'          => $item->event_type,
					'action'         => $item->action,
					'action_label'   => Actions::resolve_label( (string) $item->action ),
					'severity'       => $item->severity,
					'severity_label' => Severity::resolve_label( (string) $item->severity ),
					'ip'             => $item->ip_address,
					'message'        => $item->message,
					'before_data'    => $item->before_data,
					'after_data'     => $item->after_data,
					'context'        => $item->context,
				);
			}
		}

		return $this->success_response(
			array(
				'items'      => $formatted_items,

				'pagination' => array(
					'page'     => $page,
					'per_page' => $per_page,
					'total'    => $total,
				),
			)
		);
	}

	/**
	 * Get log details.
	 *
	 * @param WP_REST_Request $request Request object.
	 *
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function get_log_details( WP_REST_Request $request ) {

		$id = absint( $request['id'] );

		$item = $this->logs_model->get_log_by_id( $id );

		if ( empty( $item ) ) {

			return $this->error_response(
				'log_not_found',
				'Log not found',
				404
			);
		}

		$user = get_user_by(
			'id',
			$item->user_id
		);

		return $this->success_response(
			array(
				'id'             => (int) $item->id,
				'date'           => Helpers::format_timestamp_for_display( $item->timestamp ),
				'timestamp'      => $item->timestamp,
				'user'           => $user ? ( $user->display_name ? $user->display_name : $user->user_login ) : 'System',
				'event'          => $item->event_type,
				'event_label'    => Events::resolve_label( (string) $item->event_type ),
				'severity'       => $item->severity,
				'severity_label' => Severity::resolve_label( (string) $item->severity ),
				'ip'             => $item->ip_address,
				'message'        => $item->message,
				'before_data'    => $item->before_data,
				'after_data'     => $item->after_data,
				'context'        => $item->context,
				'object_type'    => $item->object_type,
				'object_id'      => $item->object_id,
				'action'         => $item->action,
				'action_label'   => Actions::resolve_label( (string) $item->action ),
				'site_id'        => $item->site_id,
				'user_id'        => $item->user_id,
			)
		);
	}

	/**
	 * Get statistics.
	 *
	 * @return \WP_REST_Response
	 */
	public function get_stats() {

		return $this->success_response(
			$this->logs_model->get_stats()
		);
	}

	/**
	 * Get filter autocomplete options.
	 *
	 * @param  WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function get_filter_options( WP_REST_Request $request ) {

		$type   = sanitize_key( (string) $request->get_param( 'type' ) );
		$search = sanitize_text_field( (string) $request->get_param( 'search' ) );
		$items  = array();

		if ( 'users' === $type ) {

			$user_rows = $this->logs_model->get_distinct_users( $search, 50 );

			foreach ( $user_rows as $row ) {
				$label = ! empty( $row->display_name )
				? $row->display_name
				: ( ! empty( $row->user_login ) ? $row->user_login : sprintf( 'User #%d', (int) $row->user_id ) );

				$items[] = array(
					'value' => (int) $row->user_id,
					'label' => sprintf( '%s (#%d)', $label, (int) $row->user_id ),
				);
			}

			return $this->success_response( array( 'items' => $items ) );
		}

		if ( 'events' === $type ) {

			$event_rows = $this->logs_model->get_distinct_event_types( $search, 100 );

			foreach ( $event_rows as $event_value ) {
				$items[] = array(
					'value' => $event_value,
					'label' => Events::resolve_label( (string) $event_value ),
				);
			}

			return $this->success_response( array( 'items' => $items ) );
		}

		if ( 'ids' === $type ) {

			$id_rows = $this->logs_model->get_distinct_ids( $search, 100 );

			foreach ( $id_rows as $id_value ) {
				$items[] = array(
					'value' => (int) $id_value,
					'label' => sprintf( '#%d', (int) $id_value ),
				);
			}

			return $this->success_response( array( 'items' => $items ) );
		}

		return $this->success_response( array( 'items' => array() ) );
	}
}
