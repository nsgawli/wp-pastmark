<?php // phpcs:ignore WordPress.Files.FileName.NotHyphenatedLowercase

namespace Pastmark\ActivityLoggers\Redirection;

use Pastmark\ActivityLoggers\AbstractLogger;
use Pastmark\Constants\Actions;
use Pastmark\Constants\Events;
use Pastmark\Utils\ContentDiffer;
use Red_Group;
use WP_REST_Request;

defined( 'ABSPATH' ) || exit;

/**
 * Redirection redirect-group activity logger (PM-168).
 *
 * **Confirmed, not assumed: `Red_Group` (`models/group.php`) fires zero
 * native WordPress hooks of any kind.** `create()`, `update()`, `delete()`,
 * `enable()`, `disable()` are all pure `$wpdb` calls - grepped the entire
 * 500-line file for `do_action`/`apply_filters` and found nothing. This
 * isn't a Pastmark limitation to work around later; it's a hard ceiling in
 * Redirection's own code, confirmed independently of WP Activity Log
 * (whose own Redirection sensor defines a group-create alert, `10509`, but
 * never wires it to anything either - its own alerts file literally
 * comments `// Groups part - not supported yet.` immediately above it).
 *
 * The only place a group change is observable at all is Redirection's own
 * REST API (`api/api-group.php`, confirmed entirely REST-driven - its
 * admin UI has no other save path), so this logger is built entirely on
 * the standard `rest_request_before_callbacks`/`rest_request_after_
 * callbacks` filters rather than any native hook. This means group events
 * are only captured when the change goes through Redirection's own REST
 * routes - a direct database write from unrelated code would not be seen.
 * A disclosed, structural limitation (there is no other option here), not
 * an oversight.
 *
 * Bulk enable/disable/delete's `global` flag (apply the action to every
 * group matching the current admin-screen filter, rather than an explicit
 * `items` list) is not covered - only the explicit-selection path is,
 * which is what Redirection's own admin UI uses for its per-row and
 * multi-select actions. Revisit if real use of the `global`-filtered path
 * surfaces.
 */
class GroupActivityLogger extends AbstractLogger {

	/**
	 * {@inheritDoc}
	 *
	 * @var string
	 */
	protected $integration = 'redirection';

	/**
	 * Highest existing group ID immediately before a `POST .../group`
	 * (create) request's callback runs, or `null` when no create request
	 * is in flight - lets the group(s) inserted by that request be found
	 * afterward by ID range, since Redirection's own create response
	 * returns a filtered list, not the new item directly.
	 *
	 * @var int|null
	 */
	protected static $pending_max_id_before_create = null;

	/**
	 * Groups snapshotted just before a REST-driven update/bulk action
	 * writes, keyed by group ID - consumed (and unset) once the
	 * corresponding `rest_request_after_callbacks` firing logs the result.
	 *
	 * @var array<int, Red_Group>
	 */
	protected static $pending_before = array();

	/**
	 * Constructor.
	 */
	public function __construct() {

		parent::__construct();

		$this->register_hooks();
	}

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	protected function register_hooks(): void {

		add_filter( 'rest_request_before_callbacks', $this->guarded( array( $this, 'capture_before' ) ), 10, 3 );

		add_filter( 'rest_request_after_callbacks', $this->guarded( array( $this, 'log_after' ) ), 10, 3 );
	}

	/**
	 * Redirection's own REST namespace.
	 *
	 * @return string
	 */
	protected function get_namespace(): string {

		return defined( 'REDIRECTION_API_NAMESPACE' ) ? REDIRECTION_API_NAMESPACE : 'redirection/v1';
	}

	/**
	 * `rest_request_before_callbacks` filter callback - snapshots
	 * whatever a group route is about to change, before it changes. Fires
	 * for *every* REST request site-wide, so bails out immediately for
	 * anything that isn't a POST to one of Redirection's own group routes.
	 * Must return `$response` unmodified.
	 *
	 * @param mixed           $response Current response (null unless already short-circuited).
	 * @param array           $handler  Matched route handler (unused).
	 * @param WP_REST_Request $request  The REST request.
	 * @return mixed
	 */
	public function capture_before( $response, $handler, $request ) {

		if ( null !== $response || ! ( $request instanceof WP_REST_Request ) || 'POST' !== $request->get_method() ) {
			return $response;
		}

		if ( ! class_exists( 'Red_Group' ) ) {
			return $response;
		}

		$namespace = $this->get_namespace();
		$route     = $request->get_route();

		if ( '/' . $namespace . '/group' === $route ) {
			global $wpdb;
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- one-off request-scoped read against Redirection's own table, nothing to cache.
			self::$pending_max_id_before_create = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT MAX(id) FROM %i', $wpdb->prefix . 'redirection_groups' ) );
			return $response;
		}

		if ( 1 === preg_match( '#^/' . preg_quote( $namespace, '#' ) . '/group/(\d+)$#', $route, $matches ) ) {
			$this->snapshot_group( (int) $matches[1] );
			return $response;
		}

		if ( 1 === preg_match( '#^/' . preg_quote( $namespace, '#' ) . '/bulk/group/(delete|enable|disable)$#', $route ) ) {
			foreach ( $this->extract_bulk_ids( $request ) as $id ) {
				$this->snapshot_group( $id );
			}
			return $response;
		}

		return $response;
	}

	/**
	 * `rest_request_after_callbacks` filter callback - logs whatever
	 * changed, now that the write has happened. Must return `$response`
	 * unmodified.
	 *
	 * @param mixed           $response The route's own response.
	 * @param array           $handler  Matched route handler (unused).
	 * @param WP_REST_Request $request  The REST request.
	 * @return mixed
	 */
	public function log_after( $response, $handler, $request ) {

		if ( ! ( $request instanceof WP_REST_Request ) || 'POST' !== $request->get_method() ) {
			return $response;
		}

		if ( ! class_exists( 'Red_Group' ) ) {
			return $response;
		}

		$namespace = $this->get_namespace();
		$route     = $request->get_route();

		if ( '/' . $namespace . '/group' === $route ) {
			$this->log_created_groups();
			return $response;
		}

		if ( 1 === preg_match( '#^/' . preg_quote( $namespace, '#' ) . '/group/(\d+)$#', $route, $matches ) ) {
			$this->log_updated_group( (int) $matches[1] );
			return $response;
		}

		if ( 1 === preg_match( '#^/' . preg_quote( $namespace, '#' ) . '/bulk/group/(delete|enable|disable)$#', $route, $matches ) ) {
			$this->log_bulk_action( $matches[1] );
			return $response;
		}

		return $response;
	}

	/**
	 * Extract the explicit `items` ID list from a bulk-action request.
	 *
	 * @param WP_REST_Request $request The REST request.
	 * @return int[]
	 */
	protected function extract_bulk_ids( WP_REST_Request $request ): array {

		$params = $request->get_params();

		if ( empty( $params['items'] ) || ! is_array( $params['items'] ) ) {
			return array();
		}

		return array_map( 'intval', $params['items'] );
	}

	/**
	 * Snapshot one group by ID, if it currently exists.
	 *
	 * @param int $id Group ID.
	 * @return void
	 */
	protected function snapshot_group( int $id ): void {

		$existing = Red_Group::get( $id );

		if ( $existing instanceof Red_Group ) {
			self::$pending_before[ $id ] = $existing;
		}
	}

	/**
	 * Log every group created by the just-completed `POST .../group`
	 * request, found by comparing the highest ID before against the
	 * current table contents (Redirection's own create response returns a
	 * filtered list, not the new item's ID directly).
	 *
	 * @return void
	 */
	protected function log_created_groups(): void {

		if ( null === self::$pending_max_id_before_create ) {
			return;
		}

		$max_before = self::$pending_max_id_before_create;

		self::$pending_max_id_before_create = null;

		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- one-off request-scoped read against Redirection's own table, nothing to cache.
		$new_ids = $wpdb->get_col( $wpdb->prepare( 'SELECT id FROM %i WHERE id > %d ORDER BY id ASC', $wpdb->prefix . 'redirection_groups', $max_before ) );

		foreach ( $new_ids as $id ) {

			$group = Red_Group::get( (int) $id );

			if ( ! ( $group instanceof Red_Group ) ) {
				continue;
			}

			$this->insert_event_log(
				Events::REDIRECTION,
				Actions::REDIRECT_GROUP_CREATE,
				array(
					'object_type' => 'redirect_group',
					'object_id'   => $group->get_id(),
					'message'     => sprintf( 'Redirect group created: "%s".', $group->get_name() ),
					'after_data'  => $this->encode_group_data( $this->prepare_group_data( $group ) ),
					'context'     => $this->get_common_context(),
				)
			);
		}
	}

	/**
	 * Log an edited group, diffed against its pre-write snapshot.
	 *
	 * @param int $id Group ID.
	 * @return void
	 */
	protected function log_updated_group( int $id ): void {

		if ( ! array_key_exists( $id, self::$pending_before ) ) {
			return;
		}

		$before = self::$pending_before[ $id ];

		unset( self::$pending_before[ $id ] );

		// `$clear = true` is required here - confirmed during this ticket's
		// own live verification: `Red_Group::get()` (models/group.php)
		// keeps a static in-process row cache keyed by ID, and
		// `Red_Group::update()` never invalidates it. Without forcing a
		// fresh read, this call would silently return the same pre-write
		// row already held in `$before`, making every genuine edit look
		// like a no-op and never logging it.
		$group = Red_Group::get( $id, true );

		if ( ! ( $group instanceof Red_Group ) ) {
			return;
		}

		$before_data = $this->prepare_group_data( $before );
		$after_data  = $this->prepare_group_data( $group );

		if ( $before_data === $after_data ) {
			return;
		}

		$before_json = $this->encode_group_data( $before_data );
		$after_json  = $this->encode_group_data( $after_data );

		$diff = ContentDiffer::diff( $before_json, $after_json );

		$log_data = array(
			'object_type' => 'redirect_group',
			'object_id'   => $id,
			'message'     => sprintf( 'Redirect group updated: "%s".', $group->get_name() ),
			'context'     => $this->get_common_context(),
		);

		if ( null !== $diff ) {
			$log_data['after_data'] = wp_json_encode( array( 'group_diff' => $diff ) );
		} else {
			$log_data['before_data'] = $before_json;
			$log_data['after_data']  = $after_json;
		}

		$this->insert_event_log( Events::REDIRECTION, Actions::REDIRECT_GROUP_UPDATE, $log_data );
	}

	/**
	 * Log a bulk delete/enable/disable action against every snapshotted
	 * group - the row is gone by the time this runs for `delete`, so the
	 * pre-write snapshot is what's actually logged, not a fresh re-fetch.
	 *
	 * @param string $action `'delete'`, `'enable'`, or `'disable'`.
	 * @return void
	 */
	protected function log_bulk_action( string $action ): void {

		$action_map = array(
			'delete'  => Actions::REDIRECT_GROUP_DELETE,
			'enable'  => Actions::REDIRECT_GROUP_ENABLE,
			'disable' => Actions::REDIRECT_GROUP_DISABLE,
		);

		if ( ! isset( $action_map[ $action ] ) ) {
			return;
		}

		foreach ( self::$pending_before as $id => $before ) {

			unset( self::$pending_before[ $id ] );

			if ( 'delete' === $action ) {

				$this->insert_event_log(
					Events::REDIRECTION,
					Actions::REDIRECT_GROUP_DELETE,
					array(
						'object_type' => 'redirect_group',
						'object_id'   => $id,
						'message'     => sprintf( 'Redirect group deleted: "%s".', $before->get_name() ),
						'before_data' => $this->encode_group_data( $this->prepare_group_data( $before ) ),
						'context'     => $this->get_common_context(),
					)
				);

				continue;
			}

			$enabled = ( 'enable' === $action );

			$this->insert_event_log(
				Events::REDIRECTION,
				$action_map[ $action ],
				array(
					'object_type' => 'redirect_group',
					'object_id'   => $id,
					'message'     => sprintf( 'Redirect group "%s" %s.', $before->get_name(), $enabled ? 'enabled' : 'disabled' ),
					'after_data'  => wp_json_encode(
						array(
							'name'    => $before->get_name(),
							'enabled' => $enabled,
						)
					),
					'context'     => $this->get_common_context(),
				)
			);
		}
	}

	/**
	 * Reduce a group to the fields worth diffing/storing - drops `id`
	 * (already the row's own `object_id`) and the volatile `redirects`
	 * count (the number of rules in the group, which changes independently
	 * of any real group-level setting and would make every save look
	 * changed even when nothing about the group itself did).
	 *
	 * @param Red_Group $group Redirect group.
	 * @return array
	 */
	protected function prepare_group_data( Red_Group $group ): array {

		$data = $group->to_json();

		unset( $data['id'], $data['redirects'] );

		return $data;
	}

	/**
	 * Encode group data for diffing/storage.
	 *
	 * @param array $data Group data.
	 * @return string
	 */
	protected function encode_group_data( array $data ): string {

		return (string) wp_json_encode( $data, JSON_PRETTY_PRINT );
	}
}
