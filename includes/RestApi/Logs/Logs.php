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

		$target = $this->resolve_object_reference( $item );

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
				'object_label'   => $target['label'],
				'object_url'     => $target['url'],
				'action'         => $item->action,
				'action_label'   => Actions::resolve_label( (string) $item->action ),
				'site_id'        => $item->site_id,
				'user_id'        => $item->user_id,
			)
		);
	}

	/**
	 * Resolve a human-readable label (and admin edit link, where
	 * possible) for the object a log entry was recorded against.
	 *
	 * `object_type` on a log row is either 'user', a handful of
	 * plugin-defined labels (comment, review, nav_menu, nav_menu_item,
	 * product_cat, shop_order, ...), or - for content types, which is most
	 * loggers - the actual WordPress/WooCommerce post type (post, page,
	 * attachment, product, shop_coupon, ...). Rather than enumerate every
	 * post type, anything not explicitly matched below falls through to
	 * `resolve_post_reference()`, since a real, resolvable `object_id`
	 * on anything else is a `WP_Post` ID in practice.
	 *
	 * Returns nulls when there's no object, when the acting user IS the
	 * object (nothing extra to show), or when the referenced object no
	 * longer exists (e.g. already deleted).
	 *
	 * @param object $item Raw log row.
	 * @return array{label: string|null, url: string|null}
	 */
	protected function resolve_object_reference( $item ): array {

		$empty = array(
			'label' => null,
			'url'   => null,
		);

		if ( empty( $item->object_id ) || (int) $item->object_id === (int) $item->user_id ) {
			return $empty;
		}

		$object_id = (int) $item->object_id;

		switch ( $item->object_type ) {

			case 'user':
				return $this->resolve_user_reference( $object_id );

			case 'comment':
			case 'review':
				return $this->resolve_comment_reference( $object_id );

			case 'nav_menu':
				return $this->resolve_nav_menu_reference( $object_id );

			case 'product_cat':
				return $this->resolve_term_reference( $object_id, 'product_cat' );

			case 'shop_order':
				return $this->resolve_order_reference( $object_id );

			default:
				return $this->resolve_post_reference( $object_id );
		}
	}

	/**
	 * Resolve a target reference for a user.
	 *
	 * @param int $user_id User ID.
	 * @return array{label: string|null, url: string|null}
	 */
	protected function resolve_user_reference( int $user_id ): array {

		$user = get_user_by( 'id', $user_id );

		if ( ! $user ) {
			return array(
				'label' => null,
				'url'   => null,
			);
		}

		return array(
			'label' => $user->display_name ? $user->display_name : $user->user_login,
			'url'   => admin_url( 'user-edit.php?user_id=' . $user_id ),
		);
	}

	/**
	 * Resolve a target reference for a `WP_Post` - covers ordinary posts
	 * and pages as well as CPT-backed content (attachments, WooCommerce
	 * products, product variations, coupons, nav menu items, ...).
	 *
	 * @param int $post_id Post ID.
	 * @return array{label: string|null, url: string|null}
	 */
	protected function resolve_post_reference( int $post_id ): array {

		$post = get_post( $post_id );

		if ( ! $post ) {
			return array(
				'label' => null,
				'url'   => null,
			);
		}

		$edit_link = get_edit_post_link( $post_id, 'raw' );

		return array(
			'label' => $post->post_title ? $post->post_title : sprintf( '#%d', $post_id ),
			'url'   => $edit_link ? $edit_link : null,
		);
	}

	/**
	 * Resolve a target reference for a comment (or WooCommerce review,
	 * which is comment-backed).
	 *
	 * @param int $comment_id Comment ID.
	 * @return array{label: string|null, url: string|null}
	 */
	protected function resolve_comment_reference( int $comment_id ): array {

		$comment = get_comment( $comment_id );

		if ( ! $comment ) {
			return array(
				'label' => null,
				'url'   => null,
			);
		}

		$snippet = wp_strip_all_tags( $comment->comment_content );
		$snippet = mb_strlen( $snippet ) > 60 ? mb_substr( $snippet, 0, 60 ) . '…' : $snippet;

		return array(
			'label' => sprintf( '%s: %s', $comment->comment_author, $snippet ? $snippet : __( '(empty)', 'logtrail' ) ),
			'url'   => get_edit_comment_link( $comment_id ),
		);
	}

	/**
	 * Resolve a target reference for a nav menu.
	 *
	 * @param int $menu_id Menu (term) ID.
	 * @return array{label: string|null, url: string|null}
	 */
	protected function resolve_nav_menu_reference( int $menu_id ): array {

		$menu = wp_get_nav_menu_object( $menu_id );

		if ( ! $menu || is_wp_error( $menu ) ) {
			return array(
				'label' => null,
				'url'   => null,
			);
		}

		return array(
			'label' => $menu->name,
			'url'   => admin_url( 'nav-menus.php?action=edit&menu=' . $menu_id ),
		);
	}

	/**
	 * Resolve a target reference for a taxonomy term.
	 *
	 * @param int    $term_id Term ID.
	 * @param string $taxonomy Taxonomy slug.
	 * @return array{label: string|null, url: string|null}
	 */
	protected function resolve_term_reference( int $term_id, string $taxonomy ): array {

		$term = get_term( $term_id, $taxonomy );

		if ( ! $term || is_wp_error( $term ) ) {
			return array(
				'label' => null,
				'url'   => null,
			);
		}

		$edit_link = get_edit_term_link( $term_id, $taxonomy );

		return array(
			'label' => $term->name,
			'url'   => is_wp_error( $edit_link ) ? null : $edit_link,
		);
	}

	/**
	 * Resolve a target reference for a WooCommerce order.
	 *
	 * Goes through `wc_get_order()` rather than `get_post()` since an
	 * order under HPOS storage isn't a `WP_Post` at all.
	 *
	 * @param int $order_id Order ID.
	 * @return array{label: string|null, url: string|null}
	 */
	protected function resolve_order_reference( int $order_id ): array {

		if ( ! function_exists( 'wc_get_order' ) ) {
			return array(
				'label' => null,
				'url'   => null,
			);
		}

		$order = wc_get_order( $order_id );

		if ( ! $order ) {
			return array(
				'label' => null,
				'url'   => null,
			);
		}

		return array(
			'label' => sprintf( 'Order #%s', $order->get_order_number() ),
			'url'   => method_exists( $order, 'get_edit_order_url' ) ? $order->get_edit_order_url() : null,
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

		$type        = sanitize_key( (string) $request->get_param( 'type' ) );
		$search      = sanitize_text_field( (string) $request->get_param( 'search' ) );
		$limit_param = $request->get_param( 'limit' );
		$values_raw  = (string) $request->get_param( 'values' );
		$items       = array();

		// `values` resolves an exact set of already-selected values to their
		// display labels (e.g. re-hydrating the advanced filters form),
		// regardless of the default/search result-set limit.
		$values = array();

		if ( '' !== trim( $values_raw ) ) {

			$values = array_filter(
				array_map( 'trim', explode( ',', $values_raw ) ),
				static function ( $value ) {
					return '' !== $value;
				}
			);
		}

		if ( 'users' === $type ) {

			if ( ! empty( $values ) ) {
				$user_rows = $this->logs_model->get_users_by_ids( $values );
			} else {
				$limit     = $limit_param ? max( 1, (int) $limit_param ) : 50;
				$user_rows = $this->logs_model->get_distinct_users( $search, $limit );
			}

			foreach ( $user_rows as $row ) {
				$items[] = array(
					'value' => (int) $row->user_id,
					'label' => ! empty( $row->display_name )
						? $row->display_name
						: ( ! empty( $row->user_login ) ? $row->user_login : sprintf( 'User #%d', (int) $row->user_id ) ),
				);
			}

			return $this->success_response( array( 'items' => $items ) );
		}

		if ( 'events' === $type ) {

			if ( ! empty( $values ) ) {
				$event_rows = $values;
			} else {
				$limit      = $limit_param ? max( 1, (int) $limit_param ) : 100;
				$event_rows = $this->logs_model->get_distinct_event_types( $search, $limit );
			}

			foreach ( $event_rows as $event_value ) {
				$items[] = array(
					'value' => $event_value,
					'label' => Events::resolve_label( (string) $event_value ),
				);
			}

			return $this->success_response( array( 'items' => $items ) );
		}

		if ( 'ids' === $type ) {

			if ( ! empty( $values ) ) {
				$id_rows = $this->logs_model->get_ids_that_exist( $values );
			} else {
				$limit   = $limit_param ? max( 1, (int) $limit_param ) : 100;
				$id_rows = $this->logs_model->get_distinct_ids( $search, $limit );
			}

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
