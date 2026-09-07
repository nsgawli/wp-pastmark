<?php // phpcs:ignore WordPress.Files.FileName.NotHyphenatedLowercase

namespace Pastmark\ActivityLoggers\Redirection;

use Pastmark\ActivityLoggers\AbstractLogger;
use Pastmark\Constants\Actions;
use Pastmark\Constants\Events;
use Pastmark\Constants\Severity;
use Pastmark\Utils\ContentDiffer;
use Red_Item;
use WP_REST_Request;

defined( 'ABSPATH' ) || exit;

/**
 * Redirection single-redirect-rule activity logger (PM-168).
 *
 * Confirmed against the real installed plugin (Redirection 5.9.0):
 * redirect rules live in a dedicated DB table (`{$wpdb->prefix}
 * redirection_items`, `database/schema/latest.php`), not options or post
 * meta. Real native hooks confirmed via `models/redirect/redirect.php`:
 *
 * - `redirection_create_redirect` (filter, fires inside `Red_Item::
 *   create()`, before the insert) and `redirection_update_redirect`
 *   (filter, fires inside `Red_Item::update()`, before the write) are two
 *   *different* filter names for the two *different* code paths - used
 *   here purely as a create-vs-update signal (whichever one fires
 *   immediately before `redirection_redirect_updated` tells us which it
 *   was), not to bridge old/new data directly.
 * - `redirection_redirect_updated` (action) fires for **both** create and
 *   update, always as `( $id, $new_item )` - confirmed by reading both
 *   `do_action()` call sites directly (`redirect.php:642` for create,
 *   `redirect.php:682` for update); there is no `redirection_redirect_
 *   created` hook at all, despite that being a reasonable guess.
 * - `redirection_redirect_deleted` (action) hands over the **full old
 *   item directly** as its only argument (`redirect.php:599`,
 *   `do_action( 'redirection_redirect_deleted', $this )`) - the one place
 *   Redirection's own hooks need no bridging trick at all.
 * - `redirection_redirect_enabled`/`_disabled` (actions) each only hand
 *   over the bare integer ID (`redirect.php:843`/`856`) - the post-toggle
 *   state is fetched fresh.
 *
 * **The one real gap, and how it's bridged:** none of the hooks above hand
 * over the *old* state for a genuine edit - `redirection_update_redirect`
 * only receives the new `$data` array, no ID. Confirmed (by reading
 * `api/api-redirect.php`) that Redirection's own admin UI is entirely
 * REST-driven and the update route is `POST /redirection/v1/redirect/
 * (?P<id>[\d]+)` - so the one missing piece (the ID, *before* the write
 * happens) is captured via the standard, documented `rest_request_before_
 * callbacks` filter, which fires after permission checks but before the
 * route's own callback runs. This mirrors WP Activity Log's own real
 * sensor for this exact same gap (confirmed by reading its source before
 * this class was written), but targets the standard, better-documented
 * `rest_request_before_callbacks` hook rather than the lower-level
 * `rest_dispatch_request` WSAL uses. A save made through a non-REST path
 * (WP-CLI, direct PHP, a future importer) has no snapshot available and
 * still logs a "redirect updated" event, just without a before/after
 * diff - a disclosed, narrow limitation, not a silent gap (see
 * `log_updated()`'s own docblock).
 *
 * Confirmed via `includes/import-export/class-redirect-repository.php`
 * that bulk import reuses these exact same `Red_Item::create()`/`update()`/
 * `enable()`/`disable()` methods per row (no separate batch-SQL path that
 * would bypass these hooks), so this logger also correctly captures
 * imported redirects - though only newly-*created* ones get a full diff
 * (imported *updates* to already-existing rules go through the same
 * REST-only snapshot gap as any other non-REST update).
 */
class RedirectActivityLogger extends AbstractLogger {

	/**
	 * {@inheritDoc}
	 *
	 * @var string
	 */
	protected $integration = 'redirection';

	/**
	 * Whichever of `redirection_create_redirect`/`redirection_update_
	 * redirect` fired most recently but hasn't yet been consumed by
	 * `redirection_redirect_updated` - `'create'`, `'update'`, or `null`.
	 *
	 * @var string|null
	 */
	protected static $pending_state = null;

	/**
	 * Redirect rows snapshotted just before a REST-driven update writes,
	 * keyed by redirect ID - consumed (and unset) the moment `redirection_
	 * redirect_updated` fires for that same ID.
	 *
	 * @var array<int, Red_Item>
	 */
	protected static $pending_before = array();

	/**
	 * Constructor.
	 */
	public function __construct() {

		parent::__construct();

		$this->register_hooks();
		$this->register_events();
	}

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	protected function register_hooks(): void {

		add_filter( 'redirection_create_redirect', $this->guarded( array( $this, 'mark_pending_create' ) ), 10, 1 );

		add_filter( 'redirection_update_redirect', $this->guarded( array( $this, 'mark_pending_update' ) ), 10, 1 );

		add_action( 'redirection_redirect_updated', $this->guarded( array( $this, 'log_created_or_updated' ) ), 10, 2 );

		add_action( 'redirection_redirect_deleted', $this->guarded( array( $this, 'log_deleted' ) ), 10, 1 );

		add_action( 'redirection_redirect_enabled', $this->guarded( array( $this, 'log_enabled' ) ), 10, 1 );

		add_action( 'redirection_redirect_disabled', $this->guarded( array( $this, 'log_disabled' ) ), 10, 1 );

		// The one REST-assisted piece - see this class's own docblock for
		// why it's needed and what happens when it isn't available.
		add_filter( 'rest_request_before_callbacks', $this->guarded( array( $this, 'capture_before_update' ) ), 10, 3 );
	}

	/**
	 * Declare the shared `redirection` event group - both this logger's
	 * rule-level actions and `GroupActivityLogger`'s group-level actions
	 * live under this one group, so only this logger (listed first in
	 * `RedirectionIntegration::get_logger_classes()`) registers it.
	 *
	 * @return void
	 */
	protected function register_events(): void {

		add_filter( 'pastmark_registered_events', array( $this, 'add_event_group' ) );
	}

	/**
	 * `pastmark_registered_events` callback - adds Redirection's event
	 * group.
	 *
	 * @param array $events Registered events, keyed by event type.
	 * @return array
	 */
	public function add_event_group( array $events ): array {

		$events[ Events::REDIRECTION ] = array(
			'label'   => __( 'Redirection', 'pastmark' ),
			'source'  => 'redirection',
			'actions' => array(
				array(
					'key'            => Actions::REDIRECT_CREATE,
					'label'          => Actions::resolve_label( Actions::REDIRECT_CREATE ),
					'description'    => __( 'A new redirect rule was created.', 'pastmark' ),
					'severity'       => Severity::INFO,
					'severity_label' => Severity::resolve_label( Severity::INFO ),
				),
				array(
					'key'            => Actions::REDIRECT_UPDATE,
					'label'          => Actions::resolve_label( Actions::REDIRECT_UPDATE ),
					'description'    => __( "A redirect rule's fields were edited.", 'pastmark' ),
					'severity'       => Severity::WARNING,
					'severity_label' => Severity::resolve_label( Severity::WARNING ),
				),
				array(
					'key'            => Actions::REDIRECT_DELETE,
					'label'          => Actions::resolve_label( Actions::REDIRECT_DELETE ),
					'description'    => __( 'A redirect rule was permanently deleted.', 'pastmark' ),
					'severity'       => Severity::WARNING,
					'severity_label' => Severity::resolve_label( Severity::WARNING ),
				),
				array(
					'key'            => Actions::REDIRECT_ENABLE,
					'label'          => Actions::resolve_label( Actions::REDIRECT_ENABLE ),
					'description'    => __( 'A redirect rule was enabled.', 'pastmark' ),
					'severity'       => Severity::INFO,
					'severity_label' => Severity::resolve_label( Severity::INFO ),
				),
				array(
					'key'            => Actions::REDIRECT_DISABLE,
					'label'          => Actions::resolve_label( Actions::REDIRECT_DISABLE ),
					'description'    => __( 'A redirect rule was disabled.', 'pastmark' ),
					'severity'       => Severity::WARNING,
					'severity_label' => Severity::resolve_label( Severity::WARNING ),
				),
				array(
					'key'            => Actions::REDIRECT_GROUP_CREATE,
					'label'          => Actions::resolve_label( Actions::REDIRECT_GROUP_CREATE ),
					'description'    => __( 'A new redirect group was created.', 'pastmark' ),
					'severity'       => Severity::INFO,
					'severity_label' => Severity::resolve_label( Severity::INFO ),
				),
				array(
					'key'            => Actions::REDIRECT_GROUP_UPDATE,
					'label'          => Actions::resolve_label( Actions::REDIRECT_GROUP_UPDATE ),
					'description'    => __( "A redirect group's name/module changed.", 'pastmark' ),
					'severity'       => Severity::INFO,
					'severity_label' => Severity::resolve_label( Severity::INFO ),
				),
				array(
					'key'            => Actions::REDIRECT_GROUP_DELETE,
					'label'          => Actions::resolve_label( Actions::REDIRECT_GROUP_DELETE ),
					'description'    => __( 'A redirect group was permanently deleted.', 'pastmark' ),
					'severity'       => Severity::WARNING,
					'severity_label' => Severity::resolve_label( Severity::WARNING ),
				),
				array(
					'key'            => Actions::REDIRECT_GROUP_ENABLE,
					'label'          => Actions::resolve_label( Actions::REDIRECT_GROUP_ENABLE ),
					'description'    => __( 'A redirect group was enabled.', 'pastmark' ),
					'severity'       => Severity::INFO,
					'severity_label' => Severity::resolve_label( Severity::INFO ),
				),
				array(
					'key'            => Actions::REDIRECT_GROUP_DISABLE,
					'label'          => Actions::resolve_label( Actions::REDIRECT_GROUP_DISABLE ),
					'description'    => __( 'A redirect group was disabled.', 'pastmark' ),
					'severity'       => Severity::WARNING,
					'severity_label' => Severity::resolve_label( Severity::WARNING ),
				),
			),
		);

		return $events;
	}

	/**
	 * `redirection_create_redirect` filter callback - marks the next
	 * `redirection_redirect_updated` firing as a genuine create. Must
	 * return `$data` unmodified.
	 *
	 * @param array $data Redirect data about to be inserted.
	 * @return array
	 */
	public function mark_pending_create( $data ) {

		self::$pending_state = 'create';

		return $data;
	}

	/**
	 * `redirection_update_redirect` filter callback - marks the next
	 * `redirection_redirect_updated` firing as a genuine update, not a
	 * create. Must return `$data` unmodified.
	 *
	 * @param array $data Redirect data about to be written.
	 * @return array
	 */
	public function mark_pending_update( $data ) {

		self::$pending_state = 'update';

		return $data;
	}

	/**
	 * `rest_request_before_callbacks` filter callback - snapshots the
	 * pre-update redirect row for the one gap Redirection's own hooks
	 * don't cover (see this class's docblock). Fires for *every* REST
	 * request site-wide, so bails out immediately for anything that isn't
	 * a POST to Redirection's own update route. Must return `$response`
	 * unmodified - this is a "did an earlier check already short-circuit
	 * this request" filter, not a value Pastmark should ever change.
	 *
	 * @param mixed           $response Current response (null unless
	 *                                  already short-circuited).
	 * @param array           $handler  Matched route handler (unused).
	 * @param WP_REST_Request $request  The REST request.
	 * @return mixed
	 */
	public function capture_before_update( $response, $handler, $request ) {

		if ( null !== $response || ! ( $request instanceof WP_REST_Request ) ) {
			return $response;
		}

		if ( 'POST' !== $request->get_method() ) {
			return $response;
		}

		$namespace = defined( 'REDIRECTION_API_NAMESPACE' ) ? REDIRECTION_API_NAMESPACE : 'redirection/v1';

		if ( 1 !== preg_match( '#^/' . preg_quote( $namespace, '#' ) . '/redirect/(\d+)$#', $request->get_route(), $matches ) ) {
			return $response;
		}

		if ( ! class_exists( 'Red_Item' ) ) {
			return $response;
		}

		$id       = (int) $matches[1];
		$existing = Red_Item::get_by_id( $id );

		if ( $existing instanceof Red_Item ) {
			self::$pending_before[ $id ] = $existing;
		}

		return $response;
	}

	/**
	 * `redirection_redirect_updated` action callback - fires for both
	 * create and update (see this class's docblock); dispatches to the
	 * right handler based on which pre-write filter fired most recently.
	 *
	 * @param int      $id   Redirect ID.
	 * @param Red_Item $item The current (post-write) redirect.
	 * @return void
	 */
	public function log_created_or_updated( $id, $item ): void {

		if ( ! ( $item instanceof Red_Item ) ) {
			return;
		}

		$state               = self::$pending_state;
		self::$pending_state = null;

		if ( 'create' === $state ) {
			$this->log_created( $item );
			return;
		}

		$id = (int) $id;

		$before = null;

		if ( array_key_exists( $id, self::$pending_before ) ) {
			$before = self::$pending_before[ $id ];
			unset( self::$pending_before[ $id ] );
		}

		$this->log_updated( $item, $before );
	}

	/**
	 * Log a new redirect rule.
	 *
	 * @param Red_Item $item The newly created redirect.
	 * @return void
	 */
	protected function log_created( Red_Item $item ): void {

		$this->insert_event_log(
			Events::REDIRECTION,
			Actions::REDIRECT_CREATE,
			array(
				'object_type' => 'redirect',
				'object_id'   => $item->get_id(),
				'message'     => sprintf( 'Redirect rule created: "%s".', $item->get_url() ),
				'after_data'  => $this->encode_redirect_data( $this->prepare_redirect_data( $item ) ),
				'context'     => $this->get_common_context(),
			)
		);
	}

	/**
	 * Log an edited redirect rule.
	 *
	 * When `$before` is available (the REST-assisted snapshot fired for
	 * this exact ID), diffs via `ContentDiffer` the same way every other
	 * integration in this codebase does, and skips a byte-identical no-op
	 * resave. When it isn't (a non-REST update path - see class docblock),
	 * still logs the update, but with the new state only and an explicit
	 * `snapshot_available => false` context flag, rather than silently
	 * dropping a real change or misleadingly presenting it as a diff.
	 *
	 * @param Red_Item      $item   The current (post-write) redirect.
	 * @param Red_Item|null $before The pre-write snapshot, if one exists.
	 * @return void
	 */
	protected function log_updated( Red_Item $item, ?Red_Item $before ): void {

		$after_data = $this->prepare_redirect_data( $item );

		if ( null === $before ) {

			$this->insert_event_log(
				Events::REDIRECTION,
				Actions::REDIRECT_UPDATE,
				array(
					'object_type' => 'redirect',
					'object_id'   => $item->get_id(),
					'message'     => sprintf( 'Redirect rule updated: "%s".', $item->get_url() ),
					'after_data'  => $this->encode_redirect_data( $after_data ),
					'context'     => array_merge( $this->get_common_context(), array( 'snapshot_available' => false ) ),
				)
			);

			return;
		}

		$before_data = $this->prepare_redirect_data( $before );

		if ( $before_data === $after_data ) {
			return;
		}

		$before_json = $this->encode_redirect_data( $before_data );
		$after_json  = $this->encode_redirect_data( $after_data );

		$diff = ContentDiffer::diff( $before_json, $after_json );

		$log_data = array(
			'object_type' => 'redirect',
			'object_id'   => $item->get_id(),
			'message'     => sprintf( 'Redirect rule updated: "%s".', $item->get_url() ),
			'context'     => $this->get_common_context(),
		);

		if ( null !== $diff ) {
			$log_data['after_data'] = wp_json_encode( array( 'redirect_diff' => $diff ) );
		} else {
			$log_data['before_data'] = $before_json;
			$log_data['after_data']  = $after_json;
		}

		$this->insert_event_log( Events::REDIRECTION, Actions::REDIRECT_UPDATE, $log_data );
	}

	/**
	 * Log a deleted redirect rule.
	 *
	 * `redirection_redirect_deleted` hands over the full old item directly
	 * (see class docblock) - no bridging needed at all.
	 *
	 * @param Red_Item $item The just-deleted redirect (still in memory).
	 * @return void
	 */
	public function log_deleted( $item ): void {

		if ( ! ( $item instanceof Red_Item ) ) {
			return;
		}

		$this->insert_event_log(
			Events::REDIRECTION,
			Actions::REDIRECT_DELETE,
			array(
				'object_type' => 'redirect',
				'object_id'   => $item->get_id(),
				'message'     => sprintf( 'Redirect rule deleted: "%s".', $item->get_url() ),
				'before_data' => $this->encode_redirect_data( $this->prepare_redirect_data( $item ) ),
				'context'     => $this->get_common_context(),
			)
		);
	}

	/**
	 * Log a redirect rule being enabled.
	 *
	 * @param int $id Redirect ID.
	 * @return void
	 */
	public function log_enabled( $id ): void {

		$this->log_toggle( (int) $id, true );
	}

	/**
	 * Log a redirect rule being disabled.
	 *
	 * @param int $id Redirect ID.
	 * @return void
	 */
	public function log_disabled( $id ): void {

		$this->log_toggle( (int) $id, false );
	}

	/**
	 * Shared enable/disable logging - only the bare ID is handed over by
	 * either native hook, so the current (post-toggle) state is fetched
	 * fresh.
	 *
	 * @param int  $id      Redirect ID.
	 * @param bool $enabled `true` for enable, `false` for disable.
	 * @return void
	 */
	protected function log_toggle( int $id, bool $enabled ): void {

		if ( ! class_exists( 'Red_Item' ) ) {
			return;
		}

		$item = Red_Item::get_by_id( $id );

		if ( ! ( $item instanceof Red_Item ) ) {
			return;
		}

		$this->insert_event_log(
			Events::REDIRECTION,
			$enabled ? Actions::REDIRECT_ENABLE : Actions::REDIRECT_DISABLE,
			array(
				'object_type' => 'redirect',
				'object_id'   => $id,
				'message'     => sprintf( 'Redirect rule "%s" %s.', $item->get_url(), $enabled ? 'enabled' : 'disabled' ),
				'after_data'  => $this->encode_redirect_data( $this->prepare_redirect_data( $item ) ),
				'context'     => $this->get_common_context(),
			)
		);
	}

	/**
	 * Reduce a redirect item to the fields worth diffing/storing - drops
	 * `id` (already the row's own `object_id`) and the volatile `hits`/
	 * `last_access` fields, which change on every real visitor hit and
	 * would make every save look changed even when nothing configuration-
	 * relevant actually was.
	 *
	 * @param Red_Item $item Redirect item.
	 * @return array
	 */
	protected function prepare_redirect_data( Red_Item $item ): array {

		$data = $item->to_json();

		unset( $data['id'], $data['hits'], $data['last_access'] );

		return $data;
	}

	/**
	 * Encode redirect data for diffing/storage.
	 *
	 * `ContentDiffer` diffs line-by-line; the default `wp_json_encode()`
	 * single-line output would make any change look like "the whole line
	 * changed" and defeat diffing entirely - the same fix PM-154 needed for
	 * ACF field-group data, applied here for the identical reason.
	 *
	 * @param array $data Redirect data.
	 * @return string
	 */
	protected function encode_redirect_data( array $data ): string {

		return (string) wp_json_encode( $data, JSON_PRETTY_PRINT );
	}
}
