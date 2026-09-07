<?php // phpcs:ignore WordPress.Files.FileName.NotHyphenatedLowercase
namespace Pastmark\RestApi\Logs;

use Pastmark\Constants\Actions;
use Pastmark\Constants\Events;
use Pastmark\Constants\Severity;
use Pastmark\Models\Pastmark_Logs;
use Pastmark\RestApi\BaseController;
use Pastmark\Utils\Helpers;
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
	 * @var Pastmark_Logs
	 */
	protected $logs_model;

	/**
	 * Constructor.
	 */
	public function __construct() {

		$this->logs_model = new Pastmark_Logs();
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
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_log' ),
					'permission_callback' => array( $this, 'permission_callback' ),
					'args'                => $this->get_create_log_params(),
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
			'/logs/new-count',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_new_logs_count' ),
					'permission_callback' => array( $this, 'permission_callback' ),
					'args'                => array_merge(
						$this->get_collection_params(),
						array(
							'since_id' => array(
								'default'           => 0,
								'sanitize_callback' => 'absint',
							),
						)
					),
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
			'page'        => array(
				'default'           => 1,
				'sanitize_callback' => 'absint',
			),

			'per_page'    => array(
				'default'           => 20,
				'sanitize_callback' => 'absint',
			),

			'search'      => array(
				'sanitize_callback' => 'sanitize_text_field',
			),

			'severity'    => array(
				'sanitize_callback' => 'sanitize_text_field',
			),

			'event'       => array(
				'sanitize_callback' => 'sanitize_text_field',
			),

			'actor_type'  => array(
				'sanitize_callback' => 'sanitize_text_field',
			),

			'integration' => array(
				'sanitize_callback' => 'sanitize_text_field',
			),

			'user_ids'    => array(
				'sanitize_callback' => 'sanitize_text_field',
			),

			'ids'         => array(
				'sanitize_callback' => 'sanitize_text_field',
			),

			'date_range'  => array(
				'default'           => 'all',
				'sanitize_callback' => 'sanitize_text_field',
			),

			'date_from'   => array(
				'sanitize_callback' => 'sanitize_text_field',
			),

			'date_to'     => array(
				'sanitize_callback' => 'sanitize_text_field',
			),

			'ip_address'  => array(
				'sanitize_callback' => 'sanitize_text_field',
			),

			'orderby'     => array(
				'default'           => 'id',
				'sanitize_callback' => 'sanitize_text_field',
			),

			'order'       => array(
				'default'           => 'DESC',
				'sanitize_callback' => 'sanitize_text_field',
			),
		);
	}

	/**
	 * REST args schema for `POST /logs` (the public Logging API's write
	 * endpoint). Only `event_type`/`action` are `required` — WordPress
	 * rejects a request missing either with a 400 before `create_log()`
	 * ever runs. Everything else is deliberately typed but not otherwise
	 * validated here: real validation (sanitization, severity checking,
	 * exclusion rules) all happens once, inside `pastmark_log_event()`
	 * (see PM-128's `Pastmark\Api\CustomEventLogger`), not duplicated here.
	 *
	 * @return array
	 */
	protected function get_create_log_params() {

		return array(
			'event_type'  => array(
				'required' => true,
				'type'     => 'string',
			),

			'action'      => array(
				'required' => true,
				'type'     => 'string',
			),

			'message'     => array(
				'type' => 'string',
			),

			'object_type' => array(
				'type' => 'string',
			),

			'object_id'   => array(
				'type' => 'integer',
			),

			'severity'    => array(
				'type' => 'string',
			),

			'context'     => array(
				'type' => 'object',
			),

			'before_data' => array(
				'type' => 'string',
			),

			'after_data'  => array(
				'type' => 'string',
			),

			'integration' => array(
				'type' => 'string',
			),
		);
	}

	/**
	 * Create a custom log entry — the REST half of the public Logging API.
	 *
	 * Delegates entirely to `pastmark_log_event()` (PM-128) rather than
	 * re-implementing sanitization/exclusion/severity-validation logic a
	 * second time; this method's only job is mapping the request's params
	 * to that function's `$args` shape and its return value to a REST
	 * response. Requires the same `manage_options` capability as every
	 * other Pastmark REST route (see `BaseController::permission_callback()`)
	 * — a scoped/token-based access model is explicitly Pro-tier future
	 * work, not built here.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function create_log( WP_REST_Request $request ) {

		$event_type = (string) $request->get_param( 'event_type' );
		$action     = (string) $request->get_param( 'action' );

		$args = array();

		foreach ( array( 'message', 'object_type', 'severity', 'before_data', 'after_data', 'integration' ) as $field ) {

			$value = $request->get_param( $field );

			if ( null !== $value ) {
				$args[ $field ] = $value;
			}
		}

		$object_id = $request->get_param( 'object_id' );

		if ( null !== $object_id ) {
			$args['object_id'] = $object_id;
		}

		$context = $request->get_param( 'context' );

		if ( is_array( $context ) ) {
			$args['context'] = $context;
		}

		$log_id = pastmark_log_event( $event_type, $action, $args );

		if ( ! $log_id ) {

			return $this->error_response(
				'create_failed',
				__( 'Unable to create the log entry. event_type/action may have been empty after sanitization, or this site\'s exclusion settings blocked it.', 'pastmark' )
			);
		}

		return $this->success_response(
			array(
				'id' => $log_id,
			)
		);
	}

	/**
	 * Get logs.
	 *
	 * Each returned item includes `actor_type` (`human`/`system`/
	 * `scheduled`/`ai_agent`) and `integration` (`core`/`woocommerce`/...)
	 * alongside the existing fields; both are also accepted as comma-
	 * separated filter params (`actor_type`, `integration`), matching how
	 * `severity`/`event` already filter.
	 *
	 * Each item also includes `object_type`, `object_id`, `object_label`,
	 * and `object_url` - the same object reference `get_log_details()`
	 * resolves, via the shared `resolve_object_reference()` logic, so a
	 * "go to object" row action doesn't need a second details fetch.
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

		$args = array_merge(
			$this->get_filter_args_from_request( $request ),
			array(
				'number'  => $per_page,
				'offset'  => $offset,
				'order'   => $request->get_param( 'order' ),
				'orderby' => $request->get_param( 'orderby' ),
			)
		);

		$items = $this->logs_model->get_logs( $args );

		$total = $this->logs_model->count_logs( $args );

		$formatted_items = array();

		if ( ! empty( $items ) ) {

			foreach ( $items as $item ) {

				$user   = get_user_by( 'id', $item->user_id );
				$target = $this->resolve_object_reference( $item );

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
					'actor_type'     => $item->actor_type,
					'integration'    => $item->integration,
					'ip'             => $item->ip_address,
					'message'        => $item->message,
					'object_type'    => $item->object_type,
					'object_id'      => $item->object_id,
					'object_label'   => $target['label'],
					'object_url'     => $target['url'],
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
	 * Build the search/severity/event/actor-type/integration/user/id/
	 * date-range/ip-address filter args shared by `get_logs()` and
	 * `get_new_logs_count()` from a request - everything `get_logs()`
	 * takes except its pagination (`page`/`per_page`) and sort
	 * (`orderby`/`order`) params, which `get_new_logs_count()` has no use
	 * for since it only ever reports a count/latest-id, never a list.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return array
	 */
	protected function get_filter_args_from_request( WP_REST_Request $request ): array {

		$date_range = $this->resolve_date_range(
			$request->get_param( 'date_range' ),
			$request->get_param( 'date_from' ),
			$request->get_param( 'date_to' )
		);

		return array(
			'search'      => $request->get_param( 'search' ),
			'severity'    => $this->parse_csv_text( $request->get_param( 'severity' ) ),
			'event'       => $this->parse_csv_text( $request->get_param( 'event' ) ),
			'actor_type'  => $this->parse_csv_text( $request->get_param( 'actor_type' ) ),
			'integration' => $this->parse_csv_text( $request->get_param( 'integration' ) ),
			'user_ids'    => $this->parse_csv_int( $request->get_param( 'user_ids' ) ),
			'ids'         => $this->parse_csv_int( $request->get_param( 'ids' ) ),
			'date_from'   => $date_range['from'],
			'date_to'     => $date_range['to'],
			'ip_address'  => $request->get_param( 'ip_address' ),
		);
	}

	/**
	 * Get the count of logs newer than `since_id` matching the current
	 * search/filters, plus the actual latest matching id - the "N new
	 * events" polling endpoint behind the Logs page's live-update badge
	 * (see `useNewEvents`/`NewEventsBanner` on the frontend).
	 *
	 * `since_id` is a baseline id the client already has (typically the
	 * newest row it loaded); `latest_id` always comes back regardless of
	 * `since_id` so the client can (re)establish that baseline - e.g. on
	 * its very first poll, or after filters/search change - without a
	 * separate request. `count` only runs the (heavier) `COUNT(*)` query
	 * when there's actually a `since_id` to compare against and the
	 * cheap `MAX(id)` lookup already shows something newer exists.
	 *
	 * @param WP_REST_Request $request Request object.
	 *
	 * @return \WP_REST_Response
	 */
	public function get_new_logs_count( WP_REST_Request $request ) {

		$since_id = absint( $request->get_param( 'since_id' ) );

		$filter_args = $this->get_filter_args_from_request( $request );

		$latest_id = $this->logs_model->get_max_id( $filter_args );

		$count = 0;

		if ( $since_id > 0 && $latest_id > $since_id ) {
			$count = $this->logs_model->count_logs(
				array_merge( $filter_args, array( 'min_id' => $since_id ) )
			);
		}

		return $this->success_response(
			array(
				'count'     => $count,
				'latest_id' => $latest_id,
			)
		);
	}

	/**
	 * Get log details.
	 *
	 * Response includes `actor_type` and `integration` alongside the
	 * existing fields (see `get_logs()`).
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
				'actor_type'     => $item->actor_type,
				'integration'    => $item->integration,
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
	 * product_cat, shop_order, site_icon, ...), or - for content types,
	 * which is most loggers - the actual WordPress/WooCommerce post type
	 * (post, page, attachment, product, shop_coupon, ...). Rather than
	 * enumerate every post type, anything not explicitly matched below
	 * falls through to `resolve_post_reference()`, but only when
	 * `object_type` is itself a real, registered post type (see the
	 * `post_type_exists()` guard below) — every native logger's
	 * object_type used this way genuinely is one.
	 *
	 * That guard matters because of PM-128/129's Public Logging API
	 * (`pastmark_log_event()` / `POST /pastmark/v1/logs`): an external
	 * caller can pass any `object_id` under any made-up `object_type`,
	 * with no guarantee it corresponds to a real WordPress post at all.
	 * Without the guard, a coincidental `object_id` match against an
	 * unrelated real post would produce a misleading "go to object" link.
	 * `site_icon` is the one native exception — it's really an attachment
	 * ID, but "site_icon" itself was never a registered post type, so it's
	 * called out explicitly rather than relying on the (now guarded)
	 * fallback.
	 *
	 * Returns nulls when there's no object, when the acting user IS the
	 * object (nothing extra to show), when the referenced object no
	 * longer exists (e.g. already deleted), or when `object_type` isn't
	 * a type this method can safely resolve.
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

			case 'site_icon':
				return $this->resolve_post_reference( $object_id );

			default:
				if ( post_type_exists( (string) $item->object_type ) ) {
					return $this->resolve_post_reference( $object_id );
				}

				return $empty;
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
			'label' => sprintf( '%s: %s', $comment->comment_author, $snippet ? $snippet : __( '(empty)', 'pastmark' ) ),
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
