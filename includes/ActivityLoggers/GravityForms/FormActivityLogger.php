<?php // phpcs:ignore WordPress.Files.FileName.NotHyphenatedLowercase

namespace Pastmark\ActivityLoggers\GravityForms;

use Pastmark\ActivityLoggers\AbstractLogger;
use Pastmark\Constants\Actions;
use Pastmark\Constants\Events;
use Pastmark\Constants\Severity;
use Pastmark\Utils\ContentDiffer;

defined( 'ABSPATH' ) || exit;

/**
 * Gravity Forms form activity logger (PM-156).
 *
 * The cleanest of Sprint 8's three forms/fields integrations, for two
 * reasons confirmed by this ticket's own source read:
 *
 * 1. **`gform_after_save_form` already tells you create-vs-update directly**
 *    (`$is_new`, from `GF_Form_CRUD_Handler::save()`) - no snapshot-presence
 *    inference needed the way ACF (PM-154) and WPForms (PM-155) both require.
 * 2. **`gform_before_delete_form` genuinely fires before the delete** - the
 *    one place these three forms integrations aren't symmetric: WPForms has
 *    no such hook at all (PM-155 falls back to core `before_delete_post`),
 *    Gravity Forms has a real, purpose-built one.
 *
 * The one real complication: unlike ACF's `acf/pre_update_field_group` and
 * WPForms' `wpforms_save_form_args` (both firing on the *same* request as
 * the eventual save), Gravity Forms' equivalent "before" snapshot point -
 * `gform_editor_pre_render`, fired when the form editor page loads - happens
 * on a genuinely *separate* HTTP request from the actual save (an AJAX call
 * to `GF_Form_CRUD_Handler::save()`, confirmed by reading both call sites).
 * A request-lifetime static cache can't bridge that gap, so this logger
 * uses a short-lived transient instead - the only one of the three forms
 * integrations that needs to.
 *
 * **Important, confirmed while researching this ticket:** `GFAPI::add_form()`/
 * `GFAPI::update_form()` (the programmatic API a developer or script would
 * normally reach for) call `GFFormsModel` directly and never touch
 * `GF_Form_CRUD_Handler::save()` at all - so they never fire
 * `gform_after_save_form`/`gform_editor_pre_render`. This is correct and
 * expected for real site-owner usage (the wp-admin Form Editor always goes
 * through the CRUD handler), but means live verification must exercise the
 * CRUD handler directly (`GFForms::get_service_container()->get(
 * GF_Save_Form_Service_Provider::GF_FORM_CRUD_HANDLER )->save(...)`), not
 * `GFAPI`, to actually exercise these hooks.
 *
 * **PM-169 addendum:** also owns `FORM_ACTIVATE`/`FORM_DEACTIVATE`
 * (`gform_post_form_activated`/`_deactivated`, straightforward single-arg
 * hooks - no snapshot needed) and, as the one logger in this integration
 * that already registers the shared `gravityforms` event group, the full
 * action list for `ConfirmationActivityLogger`/`NotificationActivityLogger`/
 * `FormSettingsActivityLogger`/`EntryActivityLogger`'s newer events too -
 * added on real user request after directly comparing this integration's
 * original 8 actions against WP Activity Log's own Gravity Forms sensor
 * (`wp-security-audit-log`, alerts 5700-5720).
 */
class FormActivityLogger extends AbstractLogger {

	/**
	 * {@inheritDoc}
	 *
	 * @var string
	 */
	protected $integration = 'gravityforms';

	/**
	 * Transient key prefix for the editor-load snapshot, per form ID.
	 */
	const SNAPSHOT_TRANSIENT_PREFIX = 'pastmark_gf_form_';

	/**
	 * How long a captured editor-load snapshot stays available to diff
	 * against - generous enough for a real editing session (a site owner
	 * opening the editor and taking their time before clicking "Update"),
	 * short enough not to accumulate stale transients indefinitely.
	 */
	const SNAPSHOT_TTL_SECONDS = HOUR_IN_SECONDS;

	/**
	 * Forms snapshotted by `capture_form_before_delete()`, keyed by form
	 * ID. Unlike the editor-load snapshot above, `gform_before_delete_form`
	 * and `gform_after_delete_form` fire in the same request (one function
	 * call apart in `GFFormsModel::delete_form()`), so a request-lifetime
	 * static cache is enough here - no transient needed.
	 *
	 * @var array<int, array|null>
	 */
	protected static $pending_deletes = array();

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

		add_action( 'gform_editor_pre_render', $this->guarded( array( $this, 'capture_form_before_edit' ) ), 10, 1 );

		add_action( 'gform_after_save_form', $this->guarded( array( $this, 'log_form_saved' ) ), 10, 3 );

		add_action( 'gform_before_delete_form', $this->guarded( array( $this, 'capture_form_before_delete' ) ), 10, 1 );

		add_action( 'gform_after_delete_form', $this->guarded( array( $this, 'log_form_deleted' ) ), 10, 1 );

		add_action( 'gform_post_form_trashed', $this->guarded( array( $this, 'log_form_trashed' ) ), 10, 1 );

		add_action( 'gform_post_form_restored', $this->guarded( array( $this, 'log_form_restored' ) ), 10, 1 );

		add_action( 'gform_post_form_duplicated', $this->guarded( array( $this, 'log_form_duplicated' ) ), 10, 2 );

		add_action( 'gform_post_form_activated', $this->guarded( array( $this, 'log_form_activated' ) ), 10, 1 );

		add_action( 'gform_post_form_deactivated', $this->guarded( array( $this, 'log_form_deactivated' ) ), 10, 1 );
	}

	/**
	 * Declare the shared `gravityforms` event group - both Gravity Forms
	 * loggers' actions live under this one group (matching how WooCommerce's
	 * five loggers all share `Events::WOOCOMMERCE`), so only this logger
	 * (constructed first in `GravityFormsIntegration::get_logger_classes()`)
	 * registers it; a second registration from `EntryActivityLogger` would
	 * just overwrite this one's contribution.
	 *
	 * Reuses the same `Actions::FORM_*`/`ENTRY_CREATE`/`NOTIFICATION_FAILED`
	 * constants PM-155's WPForms group already defined - the action *names*
	 * are identical across both forms plugins (deliberately, so the two
	 * integrations' events line up), only the `source`/event-type differ.
	 *
	 * @return void
	 */
	protected function register_events(): void {

		add_filter( 'pastmark_registered_events', array( $this, 'add_event_group' ) );
	}

	/**
	 * `pastmark_registered_events` callback - adds Gravity Forms' event group.
	 *
	 * @param array $events Registered events, keyed by event type.
	 * @return array
	 */
	public function add_event_group( array $events ): array {

		$events[ Events::GRAVITYFORMS ] = array(
			'label'   => __( 'Gravity Forms', 'pastmark' ),
			'source'  => 'gravityforms',
			'actions' => array(
				array(
					'key'            => Actions::FORM_CREATE,
					'label'          => __( 'Form Create', 'pastmark' ),
					'description'    => __( 'New Gravity Forms form created.', 'pastmark' ),
					'severity'       => Severity::INFO,
					'severity_label' => Severity::resolve_label( Severity::INFO ),
				),
				array(
					'key'            => Actions::FORM_UPDATE,
					'label'          => __( 'Form Update', 'pastmark' ),
					'description'    => __( 'Gravity Forms form fields, settings, notifications or confirmations changed.', 'pastmark' ),
					'severity'       => Severity::INFO,
					'severity_label' => Severity::resolve_label( Severity::INFO ),
				),
				array(
					'key'            => Actions::FORM_DELETE,
					'label'          => __( 'Form Delete', 'pastmark' ),
					'description'    => __( 'Gravity Forms form permanently deleted.', 'pastmark' ),
					'severity'       => Severity::WARNING,
					'severity_label' => Severity::resolve_label( Severity::WARNING ),
				),
				array(
					'key'            => Actions::FORM_TRASH,
					'label'          => __( 'Form Trash', 'pastmark' ),
					'description'    => __( 'Gravity Forms form moved to trash.', 'pastmark' ),
					'severity'       => Severity::WARNING,
					'severity_label' => Severity::resolve_label( Severity::WARNING ),
				),
				array(
					'key'            => Actions::FORM_RESTORE,
					'label'          => __( 'Form Restore', 'pastmark' ),
					'description'    => __( 'Gravity Forms form restored from trash.', 'pastmark' ),
					'severity'       => Severity::INFO,
					'severity_label' => Severity::resolve_label( Severity::INFO ),
				),
				array(
					'key'            => Actions::FORM_DUPLICATE,
					'label'          => __( 'Form Duplicate', 'pastmark' ),
					'description'    => __( 'Gravity Forms form duplicated.', 'pastmark' ),
					'severity'       => Severity::INFO,
					'severity_label' => Severity::resolve_label( Severity::INFO ),
				),
				array(
					'key'            => Actions::ENTRY_CREATE,
					'label'          => __( 'Entry Create', 'pastmark' ),
					'description'    => __( 'A visitor submitted a Gravity Forms form.', 'pastmark' ),
					'severity'       => Severity::INFO,
					'severity_label' => Severity::resolve_label( Severity::INFO ),
				),
				array(
					'key'            => Actions::NOTIFICATION_FAILED,
					'label'          => __( 'Notification Failed', 'pastmark' ),
					'description'    => __( 'A Gravity Forms notification email failed to send.', 'pastmark' ),
					'severity'       => Severity::WARNING,
					'severity_label' => Severity::resolve_label( Severity::WARNING ),
				),

				// --- PM-169 granular parity actions - see Actions::FORM_ACTIVATE's docblock. ---

				array(
					'key'            => Actions::FORM_ACTIVATE,
					'label'          => __( 'Form Activate', 'pastmark' ),
					'description'    => __( 'Gravity Forms form activated.', 'pastmark' ),
					'severity'       => Severity::INFO,
					'severity_label' => Severity::resolve_label( Severity::INFO ),
				),
				array(
					'key'            => Actions::FORM_DEACTIVATE,
					'label'          => __( 'Form Deactivate', 'pastmark' ),
					'description'    => __( 'Gravity Forms form deactivated.', 'pastmark' ),
					'severity'       => Severity::WARNING,
					'severity_label' => Severity::resolve_label( Severity::WARNING ),
				),
				array(
					'key'            => Actions::FORM_IMPORT,
					'label'          => __( 'Form Import', 'pastmark' ),
					'description'    => __( 'A Gravity Forms form was imported.', 'pastmark' ),
					'severity'       => Severity::INFO,
					'severity_label' => Severity::resolve_label( Severity::INFO ),
				),
				array(
					'key'            => Actions::FORM_EXPORT,
					'label'          => __( 'Form Export', 'pastmark' ),
					'description'    => __( 'A Gravity Forms form was exported.', 'pastmark' ),
					'severity'       => Severity::INFO,
					'severity_label' => Severity::resolve_label( Severity::INFO ),
				),
				array(
					'key'            => Actions::FORM_SETTINGS_CHANGE,
					'label'          => __( 'Form Settings Change', 'pastmark' ),
					'description'    => __( 'A Gravity Forms global plugin setting changed.', 'pastmark' ),
					'severity'       => Severity::WARNING,
					'severity_label' => Severity::resolve_label( Severity::WARNING ),
				),
				array(
					'key'            => Actions::CONFIRMATION_CREATE,
					'label'          => __( 'Confirmation Create', 'pastmark' ),
					'description'    => __( 'A confirmation was created on a Gravity Forms form.', 'pastmark' ),
					'severity'       => Severity::INFO,
					'severity_label' => Severity::resolve_label( Severity::INFO ),
				),
				array(
					'key'            => Actions::CONFIRMATION_UPDATE,
					'label'          => __( 'Confirmation Update', 'pastmark' ),
					'description'    => __( 'A confirmation was edited on a Gravity Forms form.', 'pastmark' ),
					'severity'       => Severity::INFO,
					'severity_label' => Severity::resolve_label( Severity::INFO ),
				),
				array(
					'key'            => Actions::CONFIRMATION_DELETE,
					'label'          => __( 'Confirmation Delete', 'pastmark' ),
					'description'    => __( 'A confirmation was deleted from a Gravity Forms form.', 'pastmark' ),
					'severity'       => Severity::WARNING,
					'severity_label' => Severity::resolve_label( Severity::WARNING ),
				),
				array(
					'key'            => Actions::CONFIRMATION_ACTIVATE,
					'label'          => __( 'Confirmation Activate', 'pastmark' ),
					'description'    => __( 'A confirmation was activated on a Gravity Forms form.', 'pastmark' ),
					'severity'       => Severity::INFO,
					'severity_label' => Severity::resolve_label( Severity::INFO ),
				),
				array(
					'key'            => Actions::CONFIRMATION_DEACTIVATE,
					'label'          => __( 'Confirmation Deactivate', 'pastmark' ),
					'description'    => __( 'A confirmation was deactivated on a Gravity Forms form.', 'pastmark' ),
					'severity'       => Severity::WARNING,
					'severity_label' => Severity::resolve_label( Severity::WARNING ),
				),
				array(
					'key'            => Actions::NOTIFICATION_CREATE,
					'label'          => __( 'Notification Create', 'pastmark' ),
					'description'    => __( 'A notification was created on a Gravity Forms form.', 'pastmark' ),
					'severity'       => Severity::INFO,
					'severity_label' => Severity::resolve_label( Severity::INFO ),
				),
				array(
					'key'            => Actions::NOTIFICATION_UPDATE,
					'label'          => __( 'Notification Update', 'pastmark' ),
					'description'    => __( 'A notification was edited on a Gravity Forms form.', 'pastmark' ),
					'severity'       => Severity::INFO,
					'severity_label' => Severity::resolve_label( Severity::INFO ),
				),
				array(
					'key'            => Actions::NOTIFICATION_DELETE,
					'label'          => __( 'Notification Delete', 'pastmark' ),
					'description'    => __( 'A notification was deleted from a Gravity Forms form.', 'pastmark' ),
					'severity'       => Severity::WARNING,
					'severity_label' => Severity::resolve_label( Severity::WARNING ),
				),
				array(
					'key'            => Actions::NOTIFICATION_ACTIVATE,
					'label'          => __( 'Notification Activate', 'pastmark' ),
					'description'    => __( 'A notification was activated on a Gravity Forms form.', 'pastmark' ),
					'severity'       => Severity::INFO,
					'severity_label' => Severity::resolve_label( Severity::INFO ),
				),
				array(
					'key'            => Actions::NOTIFICATION_DEACTIVATE,
					'label'          => __( 'Notification Deactivate', 'pastmark' ),
					'description'    => __( 'A notification was deactivated on a Gravity Forms form.', 'pastmark' ),
					'severity'       => Severity::WARNING,
					'severity_label' => Severity::resolve_label( Severity::WARNING ),
				),
				array(
					'key'            => Actions::ENTRY_STAR,
					'label'          => __( 'Entry Star', 'pastmark' ),
					'description'    => __( 'A Gravity Forms entry was starred.', 'pastmark' ),
					'severity'       => Severity::INFO,
					'severity_label' => Severity::resolve_label( Severity::INFO ),
				),
				array(
					'key'            => Actions::ENTRY_UNSTAR,
					'label'          => __( 'Entry Unstar', 'pastmark' ),
					'description'    => __( 'A Gravity Forms entry was unstarred.', 'pastmark' ),
					'severity'       => Severity::INFO,
					'severity_label' => Severity::resolve_label( Severity::INFO ),
				),
				array(
					'key'            => Actions::ENTRY_READ,
					'label'          => __( 'Entry Read', 'pastmark' ),
					'description'    => __( 'A Gravity Forms entry was marked read.', 'pastmark' ),
					'severity'       => Severity::INFO,
					'severity_label' => Severity::resolve_label( Severity::INFO ),
				),
				array(
					'key'            => Actions::ENTRY_UNREAD,
					'label'          => __( 'Entry Unread', 'pastmark' ),
					'description'    => __( 'A Gravity Forms entry was marked unread.', 'pastmark' ),
					'severity'       => Severity::INFO,
					'severity_label' => Severity::resolve_label( Severity::INFO ),
				),
				array(
					'key'            => Actions::ENTRY_TRASH,
					'label'          => __( 'Entry Trash', 'pastmark' ),
					'description'    => __( 'A Gravity Forms entry was moved to trash.', 'pastmark' ),
					'severity'       => Severity::WARNING,
					'severity_label' => Severity::resolve_label( Severity::WARNING ),
				),
				array(
					'key'            => Actions::ENTRY_RESTORE,
					'label'          => __( 'Entry Restore', 'pastmark' ),
					'description'    => __( 'A Gravity Forms entry was restored from trash.', 'pastmark' ),
					'severity'       => Severity::INFO,
					'severity_label' => Severity::resolve_label( Severity::INFO ),
				),
				array(
					'key'            => Actions::ENTRY_DELETE,
					'label'          => __( 'Entry Delete', 'pastmark' ),
					'description'    => __( 'A Gravity Forms entry was permanently deleted.', 'pastmark' ),
					'severity'       => Severity::WARNING,
					'severity_label' => Severity::resolve_label( Severity::WARNING ),
				),
				array(
					'key'            => Actions::ENTRY_EXPORT,
					'label'          => __( 'Entry Export', 'pastmark' ),
					'description'    => __( 'Gravity Forms entries were exported.', 'pastmark' ),
					'severity'       => Severity::INFO,
					'severity_label' => Severity::resolve_label( Severity::INFO ),
				),
				array(
					'key'            => Actions::ENTRY_NOTE_ADD,
					'label'          => __( 'Entry Note Add', 'pastmark' ),
					'description'    => __( 'A note was added to a Gravity Forms entry.', 'pastmark' ),
					'severity'       => Severity::INFO,
					'severity_label' => Severity::resolve_label( Severity::INFO ),
				),
				array(
					'key'            => Actions::ENTRY_NOTE_DELETE,
					'label'          => __( 'Entry Note Delete', 'pastmark' ),
					'description'    => __( 'A note was deleted from a Gravity Forms entry.', 'pastmark' ),
					'severity'       => Severity::WARNING,
					'severity_label' => Severity::resolve_label( Severity::WARNING ),
				),
				array(
					'key'            => Actions::ENTRY_EDIT,
					'label'          => __( 'Entry Edit', 'pastmark' ),
					'description'    => __( 'A Gravity Forms entry\'s field values were edited by an admin.', 'pastmark' ),
					'severity'       => Severity::INFO,
					'severity_label' => Severity::resolve_label( Severity::INFO ),
				),
			),
		);

		return $events;
	}

	/**
	 * Snapshot the form when its editor page loads, for later diffing
	 * against whatever gets saved from this editing session. See class
	 * docblock for why this must be a transient, not a static property.
	 *
	 * @param array $form Current (pre-edit) form object.
	 * @return void
	 */
	public function capture_form_before_edit( $form ): void {

		if ( ! is_array( $form ) || empty( $form['id'] ) ) {
			return;
		}

		set_transient(
			self::SNAPSHOT_TRANSIENT_PREFIX . (int) $form['id'],
			$this->prepare_form_data( $form ),
			self::SNAPSHOT_TTL_SECONDS
		);
	}

	/**
	 * Log a form create or update - `$is_new` comes straight from Gravity
	 * Forms itself, no inference needed.
	 *
	 * @param array $form_meta      Saved form meta.
	 * @param bool  $is_new         Whether this save created a new form.
	 * @param array $deleted_fields IDs of fields removed in this save.
	 * @return void
	 */
	public function log_form_saved( $form_meta, $is_new, $deleted_fields ): void {

		if ( ! is_array( $form_meta ) || empty( $form_meta['id'] ) ) {
			return;
		}

		$id    = (int) $form_meta['id'];
		$title = isset( $form_meta['title'] ) ? (string) $form_meta['title'] : '';

		if ( $is_new ) {

			delete_transient( self::SNAPSHOT_TRANSIENT_PREFIX . $id );

			$this->insert_event_log(
				Events::GRAVITYFORMS,
				Actions::FORM_CREATE,
				array(
					'object_type' => 'gravityforms_form',
					'object_id'   => $id,
					'message'     => sprintf( 'Gravity Forms form "%s" created.', $title ),
					'after_data'  => $this->encode_form_data( $this->prepare_form_data( $form_meta ) ),
					'context'     => $this->get_common_context(),
				)
			);

			return;
		}

		$before = get_transient( self::SNAPSHOT_TRANSIENT_PREFIX . $id );

		delete_transient( self::SNAPSHOT_TRANSIENT_PREFIX . $id );

		$after      = $this->prepare_form_data( $form_meta );
		$after_json = $this->encode_form_data( $after );

		// Comparing the *encoded* strings, not the raw `$before`/`$after`
		// PHP arrays directly - found live during this ticket's own
		// verification. A form's `fields` are `GF_Field` objects; `===`
		// between two arrays requires every element to be `===` too, and
		// for objects that means the *same instance*, not just equal
		// properties. `$before` (unserialized from a transient) and
		// `$after` (this save's own live objects) are never the same
		// instances even when their content is byte-identical, so a raw
		// `$before === $after` check would never actually detect a no-op
		// resave. Comparing the JSON strings sidesteps object identity
		// entirely.
		$before_json = is_array( $before ) ? $this->encode_form_data( $before ) : null;

		if ( null !== $before_json && $before_json === $after_json ) {
			// No actual change since the editor was opened - nothing worth a row for.
			return;
		}

		$log_data = array(
			'object_type' => 'gravityforms_form',
			'object_id'   => $id,
			'message'     => sprintf( 'Gravity Forms form "%s" updated.', $title ),
			'context'     => array_merge(
				$this->get_common_context(),
				array( 'deleted_field_ids' => array_values( (array) $deleted_fields ) )
			),
		);

		if ( null !== $before_json ) {

			$diff = ContentDiffer::diff( $before_json, $after_json );

			if ( null !== $diff ) {
				$log_data['after_data'] = wp_json_encode( array( 'form_diff' => $diff ) );
			} else {
				$log_data['before_data'] = $before_json;
				$log_data['after_data']  = $after_json;
			}
		} else {
			// No editor-load snapshot to diff against (e.g. the transient
			// expired, or this save didn't originate from a normal editor
			// session) - still a real update per Gravity Forms' own
			// `$is_new === false`, just without a "before" to compare.
			$log_data['after_data'] = $after_json;
		}

		$this->insert_event_log( Events::GRAVITYFORMS, Actions::FORM_UPDATE, $log_data );
	}

	/**
	 * Snapshot a form just before it's permanently deleted - a genuine
	 * pre-delete hook, unlike WPForms' equivalent (PM-155), which has none.
	 *
	 * @param int $form_id Form ID about to be deleted.
	 * @return void
	 */
	public function capture_form_before_delete( $form_id ): void {

		$form_id = (int) $form_id;

		$form = class_exists( 'GFAPI' ) ? \GFAPI::get_form( $form_id ) : false;

		self::$pending_deletes[ $form_id ] = is_array( $form ) ? $form : null;
	}

	/**
	 * Log form deletion, using the pre-delete snapshot above.
	 *
	 * @param int $form_id Deleted form ID.
	 * @return void
	 */
	public function log_form_deleted( $form_id ): void {

		$form_id = (int) $form_id;

		$snapshot = self::$pending_deletes[ $form_id ] ?? null;

		unset( self::$pending_deletes[ $form_id ] );

		if ( null === $snapshot ) {
			return;
		}

		$title = isset( $snapshot['title'] ) ? (string) $snapshot['title'] : '';

		$this->insert_event_log(
			Events::GRAVITYFORMS,
			Actions::FORM_DELETE,
			array(
				'object_type' => 'gravityforms_form',
				'object_id'   => $form_id,
				'severity'    => Severity::WARNING,
				'message'     => sprintf( 'Gravity Forms form "%s" deleted.', $title ),
				'before_data' => $this->encode_form_data( $this->prepare_form_data( $snapshot ) ),
				'context'     => $this->get_common_context(),
			)
		);
	}

	/**
	 * Log form trash.
	 *
	 * @param int $form_id Form ID.
	 * @return void
	 */
	public function log_form_trashed( $form_id ): void {

		$form_id = (int) $form_id;
		$title   = $this->get_form_title( $form_id );

		$this->insert_event_log(
			Events::GRAVITYFORMS,
			Actions::FORM_TRASH,
			array(
				'object_type' => 'gravityforms_form',
				'object_id'   => $form_id,
				'severity'    => Severity::WARNING,
				'message'     => sprintf( 'Gravity Forms form "%s" moved to trash.', $title ),
				'context'     => $this->get_common_context(),
			)
		);
	}

	/**
	 * Log form restore.
	 *
	 * @param int $form_id Form ID.
	 * @return void
	 */
	public function log_form_restored( $form_id ): void {

		$form_id = (int) $form_id;
		$title   = $this->get_form_title( $form_id );

		$this->insert_event_log(
			Events::GRAVITYFORMS,
			Actions::FORM_RESTORE,
			array(
				'object_type' => 'gravityforms_form',
				'object_id'   => $form_id,
				'message'     => sprintf( 'Gravity Forms form "%s" restored from trash.', $title ),
				'context'     => $this->get_common_context(),
			)
		);
	}

	/**
	 * Log form duplication.
	 *
	 * Unlike ACF/WPForms, `GFFormsModel::duplicate_form()` never calls back
	 * into the save machinery this logger also bridges (it writes form meta
	 * directly) - confirmed live during this ticket's verification, not
	 * assumed - so a duplicate here produces exactly one row, this one,
	 * with no accompanying `form_create`/`form_update` side effect.
	 *
	 * @param int $form_id     Original form ID.
	 * @param int $new_form_id New, duplicated form ID.
	 * @return void
	 */
	public function log_form_duplicated( $form_id, $new_form_id ): void {

		$new_form_id = (int) $new_form_id;
		$title       = $this->get_form_title( $new_form_id );

		$this->insert_event_log(
			Events::GRAVITYFORMS,
			Actions::FORM_DUPLICATE,
			array(
				'object_type' => 'gravityforms_form',
				'object_id'   => $new_form_id,
				'message'     => sprintf( 'Gravity Forms form "%s" duplicated (from form #%d).', $title, (int) $form_id ),
				'context'     => array_merge(
					$this->get_common_context(),
					array( 'source_form_id' => (int) $form_id )
				),
			)
		);
	}

	/**
	 * Log form activation - `gform_post_form_activated` fires after
	 * `is_active` flips back to `1` (e.g. via the form list's own toggle).
	 *
	 * @param int $form_id Form ID.
	 * @return void
	 */
	public function log_form_activated( $form_id ): void {

		$form_id = (int) $form_id;
		$title   = $this->get_form_title( $form_id );

		$this->insert_event_log(
			Events::GRAVITYFORMS,
			Actions::FORM_ACTIVATE,
			array(
				'object_type' => 'gravityforms_form',
				'object_id'   => $form_id,
				'message'     => sprintf( 'Gravity Forms form "%s" activated.', $title ),
				'context'     => $this->get_common_context(),
			)
		);
	}

	/**
	 * Log form deactivation - `gform_post_form_deactivated` fires after
	 * `is_active` flips to `0`.
	 *
	 * @param int $form_id Form ID.
	 * @return void
	 */
	public function log_form_deactivated( $form_id ): void {

		$form_id = (int) $form_id;
		$title   = $this->get_form_title( $form_id );

		$this->insert_event_log(
			Events::GRAVITYFORMS,
			Actions::FORM_DEACTIVATE,
			array(
				'object_type' => 'gravityforms_form',
				'object_id'   => $form_id,
				'severity'    => Severity::WARNING,
				'message'     => sprintf( 'Gravity Forms form "%s" deactivated.', $title ),
				'context'     => $this->get_common_context(),
			)
		);
	}

	/**
	 * Best-effort form title lookup for events that only receive a form ID.
	 *
	 * @param int $form_id Form ID.
	 * @return string
	 */
	protected function get_form_title( int $form_id ): string {

		$form = class_exists( 'GFAPI' ) ? \GFAPI::get_form( $form_id ) : false;

		return is_array( $form ) && isset( $form['title'] ) ? (string) $form['title'] : '';
	}

	/**
	 * Reduce a raw Gravity Forms form array to the settings worth diffing/
	 * storing - drops `id` (already the row's own `object_id`) and
	 * `date_created` (changes are irrelevant to what a site owner edited).
	 *
	 * @param array $form Raw form array.
	 * @return array
	 */
	protected function prepare_form_data( array $form ): array {

		unset( $form['id'], $form['date_created'] );

		return $form;
	}

	/**
	 * Encode form data for diffing/storage.
	 *
	 * `ContentDiffer` diffs line-by-line - the default `wp_json_encode()`
	 * produces a single-line string, which would make every form edit look
	 * like "the whole line changed" and defeat diffing entirely (found
	 * during PM-154's own verification, applied here from the start).
	 * Pretty-printing gives each setting its own line, so a one-field edit
	 * diffs down to just that line.
	 *
	 * @param array $data Form data (already passed through `prepare_form_data()`).
	 * @return string
	 */
	protected function encode_form_data( array $data ): string {

		return (string) wp_json_encode( $data, JSON_PRETTY_PRINT );
	}
}
