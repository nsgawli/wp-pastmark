<?php // phpcs:ignore WordPress.Files.FileName.NotHyphenatedLowercase

namespace Pastmark\ActivityLoggers\ACF;

use Pastmark\ActivityLoggers\AbstractLogger;
use Pastmark\Constants\Actions;
use Pastmark\Constants\Events;
use Pastmark\Constants\Severity;
use Pastmark\Utils\ContentDiffer;

defined( 'ABSPATH' ) || exit;

/**
 * ACF field-group activity logger (PM-154).
 *
 * ACF hands old+new field-group data over the same way its own code does
 * internally for every one of `create()`/`update()`/`delete()`/`trash()`/
 * `untrash()`/`duplicate()` on its generic internal-post-type base class
 * (`class-acf-internal-post-type.php`): `acf/pre_update_field_group` fires
 * as a *filter*, just before the write, with the incoming (already-merged)
 * field-group array - the only point at which the *old* stored array is
 * still fetchable via `acf_get_field_group()`. `acf/update_field_group`
 * fires after, as an *action*, with the new array only. Bridging the two via
 * a request-lifetime static cache (keyed by field-group ID) mirrors exactly
 * what WPForms/Gravity Forms's own form-lifecycle hooks need in PM-155/156 -
 * same shape, different plugin.
 */
class FieldGroupActivityLogger extends AbstractLogger {

	/**
	 * {@inheritDoc}
	 *
	 * @var string
	 */
	protected $integration = 'acf';

	/**
	 * Field groups snapshotted by `capture_field_group_before_update()`,
	 * keyed by field-group post ID, consumed (and unset) the moment
	 * `log_field_group_updated()` runs for that same ID.
	 *
	 * A brand-new field group is keyed `0` at snapshot time (no post ID
	 * assigned yet) and never actually looked up by that key afterwards -
	 * `log_field_group_updated()` only ever looks up the *real* post ID it's
	 * handed, so a missing entry there is exactly the "nothing to diff
	 * against" signal that means "this is a create."
	 *
	 * @var array<int, array|null>
	 */
	protected static $pending_field_groups = array();

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

		add_filter( 'acf/pre_update_field_group', $this->guarded( array( $this, 'capture_field_group_before_update' ) ), 10, 1 );

		add_action( 'acf/update_field_group', $this->guarded( array( $this, 'log_field_group_updated' ) ), 10, 1 );

		add_action( 'acf/delete_field_group', $this->guarded( array( $this, 'log_field_group_deleted' ) ), 10, 1 );

		add_action( 'acf/trash_field_group', $this->guarded( array( $this, 'log_field_group_trashed' ) ), 10, 1 );

		add_action( 'acf/untrash_field_group', $this->guarded( array( $this, 'log_field_group_restored' ) ), 10, 1 );

		add_action( 'acf/duplicate_field_group', $this->guarded( array( $this, 'log_field_group_duplicated' ) ), 10, 1 );
	}

	/**
	 * Declare the shared `acf` event group - both ACF loggers' actions live
	 * under this one group (matching how WooCommerce's five loggers all
	 * share `Events::WOOCOMMERCE`), so only this logger (constructed first
	 * in `ACFIntegration::get_logger_classes()`) registers it; a second
	 * registration from `FieldActivityLogger` would just overwrite this
	 * one's contribution, since filter callbacks for the same array key
	 * replace rather than merge.
	 *
	 * Uses the `pastmark_registered_events` filter (PM-151's settled
	 * convention, `source => 'acf'`) rather than editing
	 * `EventRegistry::get_events()` directly - unlike WooCommerce's still-
	 * hardcoded entry there (a pre-Sprint-7 refactor this ticket doesn't
	 * touch), every integration from Sprint 8 onward uses the filter, per
	 * `docs/BUILDING-AN-INTEGRATION.md`.
	 *
	 * @return void
	 */
	protected function register_events(): void {

		add_filter( 'pastmark_registered_events', array( $this, 'add_event_group' ) );
	}

	/**
	 * `pastmark_registered_events` callback - adds ACF's event group.
	 *
	 * @param array $events Registered events, keyed by event type.
	 * @return array
	 */
	public function add_event_group( array $events ): array {

		$events[ Events::ACF ] = array(
			'label'   => __( 'ACF', 'pastmark' ),
			'source'  => 'acf',
			'actions' => array(
				array(
					'key'            => Actions::FIELD_GROUP_CREATE,
					'label'          => __( 'Field Group Create', 'pastmark' ),
					'description'    => __( 'New ACF field group created.', 'pastmark' ),
					'severity'       => Severity::INFO,
					'severity_label' => Severity::resolve_label( Severity::INFO ),
				),
				array(
					'key'            => Actions::FIELD_GROUP_UPDATE,
					'label'          => __( 'Field Group Update', 'pastmark' ),
					'description'    => __( 'ACF field group settings or location rules changed.', 'pastmark' ),
					'severity'       => Severity::INFO,
					'severity_label' => Severity::resolve_label( Severity::INFO ),
				),
				array(
					'key'            => Actions::FIELD_GROUP_DELETE,
					'label'          => __( 'Field Group Delete', 'pastmark' ),
					'description'    => __( 'ACF field group permanently deleted.', 'pastmark' ),
					'severity'       => Severity::WARNING,
					'severity_label' => Severity::resolve_label( Severity::WARNING ),
				),
				array(
					'key'            => Actions::FIELD_GROUP_TRASH,
					'label'          => __( 'Field Group Trash', 'pastmark' ),
					'description'    => __( 'ACF field group moved to trash.', 'pastmark' ),
					'severity'       => Severity::WARNING,
					'severity_label' => Severity::resolve_label( Severity::WARNING ),
				),
				array(
					'key'            => Actions::FIELD_GROUP_RESTORE,
					'label'          => __( 'Field Group Restore', 'pastmark' ),
					'description'    => __( 'ACF field group restored from trash.', 'pastmark' ),
					'severity'       => Severity::INFO,
					'severity_label' => Severity::resolve_label( Severity::INFO ),
				),
				array(
					'key'            => Actions::FIELD_GROUP_DUPLICATE,
					'label'          => __( 'Field Group Duplicate', 'pastmark' ),
					'description'    => __( 'ACF field group duplicated.', 'pastmark' ),
					'severity'       => Severity::INFO,
					'severity_label' => Severity::resolve_label( Severity::INFO ),
				),
				array(
					'key'            => Actions::FIELD_CREATE,
					'label'          => __( 'Field Create', 'pastmark' ),
					'description'    => __( 'New field added to an ACF field group.', 'pastmark' ),
					'severity'       => Severity::INFO,
					'severity_label' => Severity::resolve_label( Severity::INFO ),
				),
				array(
					'key'            => Actions::FIELD_UPDATE,
					'label'          => __( 'Field Update', 'pastmark' ),
					'description'    => __( 'Settings of an existing ACF field changed.', 'pastmark' ),
					'severity'       => Severity::INFO,
					'severity_label' => Severity::resolve_label( Severity::INFO ),
				),
				array(
					'key'            => Actions::FIELD_DELETE,
					'label'          => __( 'Field Delete', 'pastmark' ),
					'description'    => __( 'Field removed from an ACF field group.', 'pastmark' ),
					'severity'       => Severity::WARNING,
					'severity_label' => Severity::resolve_label( Severity::WARNING ),
				),
			),
		);

		return $events;
	}

	/**
	 * Snapshot the stored field group before `acf/pre_update_field_group`'s
	 * write happens, keyed by its current post ID.
	 *
	 * Must return `$field_group` unmodified - this is a filter ACF applies
	 * to the array it's about to save, not a value Pastmark should ever
	 * change.
	 *
	 * @param array $field_group Incoming field-group array, about to be saved.
	 * @return array
	 */
	public function capture_field_group_before_update( $field_group ) {

		if ( ! is_array( $field_group ) ) {
			return $field_group;
		}

		$id = isset( $field_group['ID'] ) ? (int) $field_group['ID'] : 0;

		if ( $id > 0 && function_exists( 'acf_get_field_group' ) ) {
			$existing = acf_get_field_group( $id );

			self::$pending_field_groups[ $id ] = is_array( $existing ) ? $existing : null;
		}

		return $field_group;
	}

	/**
	 * Log a field-group create or update.
	 *
	 * A snapshot found for this ID means an edit - diffed via `ContentDiffer`
	 * with the exact same fallback shape `PostActivityLogger::
	 * log_post_content_changed()` already uses (PM-142): a non-null diff is
	 * stored alone, a `null` diff falls back to full before/after. No
	 * snapshot means either a genuine create, or (rarely) a direct
	 * `acf/update_field_group` call that bypassed the pre-update filter
	 * entirely - ACF's own "move field to another group" AJAX handler
	 * (`admin-field-group.php::ajax_move_field()`) does exactly this,
	 * firing the action directly with both the source and destination
	 * groups' already-current data. That rare path is mislabeled as a
	 * create here (no prior snapshot exists to diff against) rather than
	 * silently dropped - a known, narrow limitation, not in scope for this
	 * ticket to close.
	 *
	 * @param array $field_group Field-group array as saved.
	 * @return void
	 */
	public function log_field_group_updated( $field_group ): void {

		if ( ! is_array( $field_group ) || empty( $field_group['ID'] ) ) {
			return;
		}

		$id = (int) $field_group['ID'];

		$before = array_key_exists( $id, self::$pending_field_groups ) ? self::$pending_field_groups[ $id ] : null;

		unset( self::$pending_field_groups[ $id ] );

		$title = isset( $field_group['title'] ) ? (string) $field_group['title'] : '';

		if ( null === $before ) {

			$this->insert_event_log(
				Events::ACF,
				Actions::FIELD_GROUP_CREATE,
				array(
					'object_type' => 'acf-field-group',
					'object_id'   => $id,
					'message'     => sprintf( 'ACF field group "%s" created.', $title ),
					'after_data'  => $this->encode_field_group_data( $this->prepare_field_group_data( $field_group ) ),
					'context'     => $this->get_common_context(),
				)
			);

			return;
		}

		$before_data = $this->prepare_field_group_data( $before );
		$after_data  = $this->prepare_field_group_data( $field_group );

		if ( $before_data === $after_data ) {
			// Every field group in $_POST['acf_fields'] round-trips through
			// this same hook whether or not it actually changed - skip the
			// no-op ones instead of logging a row with nothing to show.
			return;
		}

		$before_json = $this->encode_field_group_data( $before_data );
		$after_json  = $this->encode_field_group_data( $after_data );

		$diff = ContentDiffer::diff( $before_json, $after_json );

		$log_data = array(
			'object_type' => 'acf-field-group',
			'object_id'   => $id,
			'message'     => sprintf( 'ACF field group "%s" updated.', $title ),
			'context'     => $this->get_common_context(),
		);

		if ( null !== $diff ) {
			$log_data['after_data'] = wp_json_encode( array( 'field_group_diff' => $diff ) );
		} else {
			$log_data['before_data'] = $before_json;
			$log_data['after_data']  = $after_json;
		}

		$this->insert_event_log( Events::ACF, Actions::FIELD_GROUP_UPDATE, $log_data );
	}

	/**
	 * Log field-group deletion.
	 *
	 * @param array $field_group Field-group array as it existed just before deletion.
	 * @return void
	 */
	public function log_field_group_deleted( $field_group ): void {

		if ( ! is_array( $field_group ) || empty( $field_group['ID'] ) ) {
			return;
		}

		$title = isset( $field_group['title'] ) ? (string) $field_group['title'] : '';

		$this->insert_event_log(
			Events::ACF,
			Actions::FIELD_GROUP_DELETE,
			array(
				'object_type' => 'acf-field-group',
				'object_id'   => (int) $field_group['ID'],
				'severity'    => Severity::WARNING,
				'message'     => sprintf( 'ACF field group "%s" deleted.', $title ),
				'before_data' => $this->encode_field_group_data( $this->prepare_field_group_data( $field_group ) ),
				'context'     => $this->get_common_context(),
			)
		);
	}

	/**
	 * Log field-group trash.
	 *
	 * @param array $field_group Field-group array.
	 * @return void
	 */
	public function log_field_group_trashed( $field_group ): void {

		if ( ! is_array( $field_group ) || empty( $field_group['ID'] ) ) {
			return;
		}

		$title = isset( $field_group['title'] ) ? (string) $field_group['title'] : '';

		$this->insert_event_log(
			Events::ACF,
			Actions::FIELD_GROUP_TRASH,
			array(
				'object_type' => 'acf-field-group',
				'object_id'   => (int) $field_group['ID'],
				'severity'    => Severity::WARNING,
				'message'     => sprintf( 'ACF field group "%s" moved to trash.', $title ),
				'context'     => $this->get_common_context(),
			)
		);
	}

	/**
	 * Log field-group restore from trash.
	 *
	 * @param array $field_group Field-group array.
	 * @return void
	 */
	public function log_field_group_restored( $field_group ): void {

		if ( ! is_array( $field_group ) || empty( $field_group['ID'] ) ) {
			return;
		}

		$title = isset( $field_group['title'] ) ? (string) $field_group['title'] : '';

		$this->insert_event_log(
			Events::ACF,
			Actions::FIELD_GROUP_RESTORE,
			array(
				'object_type' => 'acf-field-group',
				'object_id'   => (int) $field_group['ID'],
				'message'     => sprintf( 'ACF field group "%s" restored from trash.', $title ),
				'context'     => $this->get_common_context(),
			)
		);
	}

	/**
	 * Log field-group duplication.
	 *
	 * `acf/duplicate_field_group` fires with the *new* (already-saved)
	 * field group only. `duplicate_post()` gets there in two steps: a bare
	 * `wp_insert_post()` first (assigns the new post its real ID, but with
	 * no ACF data yet - bypasses `acf/pre_update_field_group`/
	 * `acf/update_field_group` entirely), *then* `update_post()` on the
	 * now-ID'd post (which does fire both). By the time
	 * `capture_field_group_before_update()` runs for that second step, the
	 * bare placeholder post already exists at that ID, so
	 * `acf_get_field_group()` finds *something* rather than nothing - this
	 * duplicate therefore also logs its own `field_group_update` row from
	 * `log_field_group_updated()` above (a near-empty-placeholder-to-full-
	 * data diff/fallback), not a `field_group_create` one. Confirmed live
	 * during this ticket's own verification pass, not assumed. This event
	 * is what actually and unambiguously records "this field group came
	 * from duplication."
	 *
	 * @param array $field_group The new, duplicated field-group array.
	 * @return void
	 */
	public function log_field_group_duplicated( $field_group ): void {

		if ( ! is_array( $field_group ) || empty( $field_group['ID'] ) ) {
			return;
		}

		$title = isset( $field_group['title'] ) ? (string) $field_group['title'] : '';

		$this->insert_event_log(
			Events::ACF,
			Actions::FIELD_GROUP_DUPLICATE,
			array(
				'object_type' => 'acf-field-group',
				'object_id'   => (int) $field_group['ID'],
				'message'     => sprintf( 'ACF field group "%s" duplicated.', $title ),
				'after_data'  => $this->encode_field_group_data( $this->prepare_field_group_data( $field_group ) ),
				'context'     => $this->get_common_context(),
			)
		);
	}

	/**
	 * Reduce a raw ACF field-group array to the settings worth diffing/
	 * storing - drops `ID` (already the row's own `object_id`) and ACF's
	 * internal `_valid` validation-cache flag, keeps everything else
	 * (title, location rules, style, placement, active status, ...).
	 *
	 * @param array $field_group Raw field-group array.
	 * @return array
	 */
	protected function prepare_field_group_data( array $field_group ): array {

		unset( $field_group['ID'], $field_group['_valid'] );

		return $field_group;
	}

	/**
	 * Encode field-group data for diffing/storage.
	 *
	 * `ContentDiffer` diffs line-by-line (built for `post_content`/comment
	 * bodies, which already have real line breaks) - the default
	 * `wp_json_encode()` produces a single-line string, which would make
	 * every field-group edit look like "the whole line changed" and defeat
	 * diffing entirely. Pretty-printing gives each setting its own line, so
	 * a one-field edit (e.g. just the location rules) diffs down to just
	 * that line, the same way a one-paragraph post edit already does.
	 *
	 * @param array $data Field-group data (already passed through `prepare_field_group_data()`).
	 * @return string
	 */
	protected function encode_field_group_data( array $data ): string {

		return (string) wp_json_encode( $data, JSON_PRETTY_PRINT );
	}
}
