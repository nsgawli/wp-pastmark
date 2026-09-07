<?php // phpcs:ignore WordPress.Files.FileName.NotHyphenatedLowercase

namespace Pastmark\ActivityLoggers\WPForms;

use Pastmark\ActivityLoggers\AbstractLogger;
use Pastmark\Constants\Actions;
use Pastmark\Constants\Events;
use Pastmark\Constants\Severity;
use Pastmark\Utils\ContentDiffer;
use WP_Post;

defined( 'ABSPATH' ) || exit;

/**
 * WPForms form activity logger (PM-155).
 *
 * WPForms hands old+new form data over the same disjointed way ACF's
 * field-group hooks do (PM-154): `wpforms_save_form_args` fires as a
 * *filter*, just before `wp_update_post()` writes the new
 * `wpforms_encode()`-d content, with the OLD row still fetchable via
 * `wpforms()->form->get()`. `wpforms_save_form` fires after, as an
 * *action*, with the new data only. Bridging the two via a request-lifetime
 * static cache (keyed by form ID) mirrors `FieldGroupActivityLogger`
 * exactly - same shape, different plugin.
 *
 * Deletion is the one place WPForms genuinely has no native "before" hook
 * at all (confirmed by this sprint's pre-ticketing source read: `delete()`
 * only fires `wpforms_delete_form` *after* every ID in the batch is already
 * gone, with IDs only) - so this logger falls back to WordPress core's own
 * `before_delete_post`, filtered to the `wpforms`/`wpforms-template` post
 * types, for the actual pre-delete snapshot, then uses `wpforms_delete_form`
 * only to confirm the batch succeeded and to emit the rows.
 *
 * **PM-170 addendum:** also extracts `FORM_RENAME` and the `CONFIRMATION_*`/
 * `NOTIFICATION_*` sub-resource events from this same before/after pair (see
 * `log_sub_resource_changes()`) - added on real user request after directly
 * comparing this integration's original 8 actions against WP Activity Log's
 * own WPForms sensor (`wp-security-audit-log`, alerts 5500-5526). Entry
 * moderation (star/read/trash/delete/notes/admin edit - WSAL's 5504/5507)
 * is deliberately NOT built: confirmed live in this environment
 * (`wpforms()->obj( 'entry' )` returns `null` - no `entry` key in
 * `WPForms\WPForms`'s class registry at all) that WPForms Lite has no entry
 * *storage* to moderate in the first place - that's a Pro-only ("View
 * Entries") feature. `ENTRY_CREATE` above still works regardless, since
 * `wpforms_process_complete` fires during submission processing whether or
 * not the entry ends up persisted anywhere. WP Activity Log's own sensor
 * registers those two hooks unconditionally too; they simply never fire on
 * a Lite-only install like this one. Revisit if a real WPForms Pro
 * environment becomes available to verify against - see
 * `docs/sprints/sprint-08.md`'s own precedent for scoping around what can
 * actually be verified live rather than built blind.
 */
class FormActivityLogger extends AbstractLogger {

	/**
	 * {@inheritDoc}
	 *
	 * @var string
	 */
	protected $integration = 'wpforms';

	/**
	 * Post types this logger (and its `before_delete_post` snapshot) cares
	 * about - real forms plus WPForms' own "save as template" post type,
	 * which is built on the exact same save/update machinery.
	 *
	 * @var string[]
	 */
	protected const FORM_POST_TYPES = array( 'wpforms', 'wpforms-template' );

	/**
	 * Forms snapshotted by `capture_form_before_update()`, keyed by form ID,
	 * consumed (and unset) the moment `log_form_updated()` runs for that
	 * same ID. A missing entry there is the "nothing to diff against"
	 * signal that means "this is a create" - see that method's docblock.
	 *
	 * @var array<int, array|null>
	 */
	protected static $pending_forms = array();

	/**
	 * Forms snapshotted by `capture_form_before_delete()`, keyed by form ID,
	 * consumed (and unset) the moment `log_forms_deleted()` runs for
	 * `wpforms_delete_form`'s confirmed batch.
	 *
	 * @var array<int, array>
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

		add_filter( 'wpforms_save_form_args', $this->guarded( array( $this, 'capture_form_before_update' ) ), 10, 1 );

		add_action( 'wpforms_save_form', $this->guarded( array( $this, 'log_form_updated' ) ), 10, 2 );

		add_action( 'wpforms_create_form', $this->guarded( array( $this, 'log_form_created' ) ), 10, 3 );

		add_action( 'wpforms_form_handler_update_status', $this->guarded( array( $this, 'log_form_status_changed' ) ), 10, 2 );

		add_action( 'wpforms_form_handler_duplicate_form', $this->guarded( array( $this, 'log_form_duplicated' ) ), 10, 3 );

		add_action( 'before_delete_post', $this->guarded( array( $this, 'capture_form_before_delete' ) ), 10, 1 );

		add_action( 'wpforms_delete_form', $this->guarded( array( $this, 'log_forms_deleted' ) ), 10, 1 );
	}

	/**
	 * Declare the shared `wpforms` event group - both WPForms loggers'
	 * actions live under this one group (matching how WooCommerce's five
	 * loggers all share `Events::WOOCOMMERCE`), so only this logger
	 * (constructed first in `WPFormsIntegration::get_logger_classes()`)
	 * registers it; a second registration from `EntryActivityLogger` would
	 * just overwrite this one's contribution, since filter callbacks for
	 * the same array key replace rather than merge.
	 *
	 * @return void
	 */
	protected function register_events(): void {

		add_filter( 'pastmark_registered_events', array( $this, 'add_event_group' ) );
	}

	/**
	 * `pastmark_registered_events` callback - adds WPForms' event group.
	 *
	 * @param array $events Registered events, keyed by event type.
	 * @return array
	 */
	public function add_event_group( array $events ): array {

		$events[ Events::WPFORMS ] = array(
			'label'   => __( 'WPForms', 'pastmark' ),
			'source'  => 'wpforms',
			'actions' => array(
				array(
					'key'            => Actions::FORM_CREATE,
					'label'          => __( 'Form Create', 'pastmark' ),
					'description'    => __( 'New WPForms form created.', 'pastmark' ),
					'severity'       => Severity::INFO,
					'severity_label' => Severity::resolve_label( Severity::INFO ),
				),
				array(
					'key'            => Actions::FORM_UPDATE,
					'label'          => __( 'Form Update', 'pastmark' ),
					'description'    => __( 'WPForms form fields, settings, notifications or confirmations changed.', 'pastmark' ),
					'severity'       => Severity::INFO,
					'severity_label' => Severity::resolve_label( Severity::INFO ),
				),
				array(
					'key'            => Actions::FORM_DELETE,
					'label'          => __( 'Form Delete', 'pastmark' ),
					'description'    => __( 'WPForms form permanently deleted.', 'pastmark' ),
					'severity'       => Severity::WARNING,
					'severity_label' => Severity::resolve_label( Severity::WARNING ),
				),
				array(
					'key'            => Actions::FORM_TRASH,
					'label'          => __( 'Form Trash', 'pastmark' ),
					'description'    => __( 'WPForms form moved to trash.', 'pastmark' ),
					'severity'       => Severity::WARNING,
					'severity_label' => Severity::resolve_label( Severity::WARNING ),
				),
				array(
					'key'            => Actions::FORM_RESTORE,
					'label'          => __( 'Form Restore', 'pastmark' ),
					'description'    => __( 'WPForms form restored from trash.', 'pastmark' ),
					'severity'       => Severity::INFO,
					'severity_label' => Severity::resolve_label( Severity::INFO ),
				),
				array(
					'key'            => Actions::FORM_DUPLICATE,
					'label'          => __( 'Form Duplicate', 'pastmark' ),
					'description'    => __( 'WPForms form duplicated.', 'pastmark' ),
					'severity'       => Severity::INFO,
					'severity_label' => Severity::resolve_label( Severity::INFO ),
				),
				array(
					'key'            => Actions::ENTRY_CREATE,
					'label'          => __( 'Entry Create', 'pastmark' ),
					'description'    => __( 'A visitor submitted a WPForms form.', 'pastmark' ),
					'severity'       => Severity::INFO,
					'severity_label' => Severity::resolve_label( Severity::INFO ),
				),
				array(
					'key'            => Actions::NOTIFICATION_FAILED,
					'label'          => __( 'Notification Failed', 'pastmark' ),
					'description'    => __( 'A WPForms notification email failed to send (best-effort detection - see docs/sprints/sprint-08.md).', 'pastmark' ),
					'severity'       => Severity::WARNING,
					'severity_label' => Severity::resolve_label( Severity::WARNING ),
				),

				// --- PM-170 granular parity actions - see Actions::FORM_RENAME's docblock. ---

				array(
					'key'            => Actions::FORM_RENAME,
					'label'          => __( 'Form Rename', 'pastmark' ),
					'description'    => __( 'WPForms form renamed.', 'pastmark' ),
					'severity'       => Severity::INFO,
					'severity_label' => Severity::resolve_label( Severity::INFO ),
				),
				array(
					'key'            => Actions::FORM_SETTINGS_CHANGE,
					'label'          => __( 'Form Settings Change', 'pastmark' ),
					'description'    => __( 'A WPForms global plugin setting changed.', 'pastmark' ),
					'severity'       => Severity::WARNING,
					'severity_label' => Severity::resolve_label( Severity::WARNING ),
				),
				array(
					'key'            => Actions::SERVICE_INTEGRATION_CHANGE,
					'label'          => __( 'Service Integration Change', 'pastmark' ),
					'description'    => __( 'A WPForms service/provider integration was added or removed.', 'pastmark' ),
					'severity'       => Severity::WARNING,
					'severity_label' => Severity::resolve_label( Severity::WARNING ),
				),
				array(
					'key'            => Actions::ADDON_ACTIVATE,
					'label'          => __( 'Addon Activate', 'pastmark' ),
					'description'    => __( 'A WPForms addon was activated.', 'pastmark' ),
					'severity'       => Severity::INFO,
					'severity_label' => Severity::resolve_label( Severity::INFO ),
				),
				array(
					'key'            => Actions::ADDON_DEACTIVATE,
					'label'          => __( 'Addon Deactivate', 'pastmark' ),
					'description'    => __( 'A WPForms addon was deactivated.', 'pastmark' ),
					'severity'       => Severity::WARNING,
					'severity_label' => Severity::resolve_label( Severity::WARNING ),
				),
				array(
					'key'            => Actions::CONFIRMATION_CREATE,
					'label'          => __( 'Confirmation Create', 'pastmark' ),
					'description'    => __( 'A confirmation was added to a WPForms form.', 'pastmark' ),
					'severity'       => Severity::INFO,
					'severity_label' => Severity::resolve_label( Severity::INFO ),
				),
				array(
					'key'            => Actions::CONFIRMATION_UPDATE,
					'label'          => __( 'Confirmation Update', 'pastmark' ),
					'description'    => __( 'A confirmation was edited on a WPForms form.', 'pastmark' ),
					'severity'       => Severity::INFO,
					'severity_label' => Severity::resolve_label( Severity::INFO ),
				),
				array(
					'key'            => Actions::CONFIRMATION_DELETE,
					'label'          => __( 'Confirmation Delete', 'pastmark' ),
					'description'    => __( 'A confirmation was removed from a WPForms form.', 'pastmark' ),
					'severity'       => Severity::WARNING,
					'severity_label' => Severity::resolve_label( Severity::WARNING ),
				),
				array(
					'key'            => Actions::NOTIFICATION_CREATE,
					'label'          => __( 'Notification Create', 'pastmark' ),
					'description'    => __( 'A notification was added to a WPForms form.', 'pastmark' ),
					'severity'       => Severity::INFO,
					'severity_label' => Severity::resolve_label( Severity::INFO ),
				),
				array(
					'key'            => Actions::NOTIFICATION_UPDATE,
					'label'          => __( 'Notification Update', 'pastmark' ),
					'description'    => __( 'A notification was edited on a WPForms form.', 'pastmark' ),
					'severity'       => Severity::INFO,
					'severity_label' => Severity::resolve_label( Severity::INFO ),
				),
				array(
					'key'            => Actions::NOTIFICATION_DELETE,
					'label'          => __( 'Notification Delete', 'pastmark' ),
					'description'    => __( 'A notification was removed from a WPForms form.', 'pastmark' ),
					'severity'       => Severity::WARNING,
					'severity_label' => Severity::resolve_label( Severity::WARNING ),
				),
				array(
					'key'            => Actions::NOTIFICATION_ACTIVATE,
					'label'          => __( 'Notification Activate', 'pastmark' ),
					'description'    => __( 'All notifications enabled on a WPForms form.', 'pastmark' ),
					'severity'       => Severity::INFO,
					'severity_label' => Severity::resolve_label( Severity::INFO ),
				),
				array(
					'key'            => Actions::NOTIFICATION_DEACTIVATE,
					'label'          => __( 'Notification Deactivate', 'pastmark' ),
					'description'    => __( 'All notifications disabled on a WPForms form.', 'pastmark' ),
					'severity'       => Severity::WARNING,
					'severity_label' => Severity::resolve_label( Severity::WARNING ),
				),
			),
		);

		return $events;
	}

	/**
	 * Snapshot the stored form before `wpforms_save_form_args`'s write
	 * happens, keyed by its form ID.
	 *
	 * Must return `$post_data` unmodified - this is a filter WPForms
	 * applies to the array it's about to hand to `wp_update_post()`, not a
	 * value Pastmark should ever change.
	 *
	 * @param array $post_data Incoming `wp_update_post()` args (`ID`, `post_title`, `post_excerpt`, `post_content`).
	 * @return array
	 */
	public function capture_form_before_update( $post_data ) {

		if ( ! is_array( $post_data ) || empty( $post_data['ID'] ) ) {
			return $post_data;
		}

		$id = (int) $post_data['ID'];

		$form_obj = wpforms()->obj( 'form' );

		$existing = $form_obj ? $form_obj->get(
			$id,
			array(
				'cap'          => false,
				'content_only' => true,
			)
		) : false;

		self::$pending_forms[ $id ] = is_array( $existing ) ? $existing : null;

		return $post_data;
	}

	/**
	 * Log a form create or update.
	 *
	 * A snapshot found for this ID means an edit - diffed via `ContentDiffer`
	 * with the exact same fallback shape `FieldGroupActivityLogger` uses
	 * (PM-154): a non-null diff is stored alone, a `null` diff falls back to
	 * full before/after. No snapshot means a genuine create in the common
	 * case, but confirmed live during this ticket's own verification that
	 * this bridge rarely fires for a real create at all - `wpforms_create_form`
	 * (below) is the primary create signal; a brand-new form's *first* save
	 * in the builder already finds the bare row `wpforms_create_form`'s own
	 * `wp_insert_post()` just wrote, so it logs as an update against that
	 * bare baseline rather than a second create - documented, not a bug,
	 * the same shape as `FieldGroupActivityLogger::log_field_group_duplicated()`'s
	 * own verified finding.
	 *
	 * @param int   $form_id Form ID actually written.
	 * @param array $form    The `wp_update_post()` args just saved (`post_content` is the new `wpforms_encode()`-d data).
	 * @return void
	 */
	public function log_form_updated( $form_id, $form ): void {

		$id = (int) $form_id;

		if ( $id <= 0 || ! is_array( $form ) ) {
			return;
		}

		$before = array_key_exists( $id, self::$pending_forms ) ? self::$pending_forms[ $id ] : null;

		unset( self::$pending_forms[ $id ] );

		// Not `wpforms_decode( $form['post_content'] )` - the array
		// `wpforms_save_form` hands over is WPForms' own pre-`wp_update_post()`
		// args, still `wp_slash()`-escaped (`wpforms_encode()`'s own doing);
		// `wp_update_post()` only unslashes it *after* this hook fires, so
		// decoding it directly here silently returned `[]` every time
		// (`wpforms_decode()`'s own JSON-error fallback) - found live during
		// this ticket's verification, not assumed. Re-fetching via the same
		// `get()` call the "before" snapshot already uses sidesteps the
		// slashing pitfall entirely and keeps both sides of the diff going
		// through one identical, already-correct code path.
		$form_obj = wpforms()->obj( 'form' );
		$after    = $form_obj ? $form_obj->get(
			$id,
			array(
				'cap'          => false,
				'content_only' => true,
			)
		) : false;
		$after    = is_array( $after ) ? $after : array();

		$title = isset( $form['post_title'] ) ? (string) $form['post_title'] : ( $after['settings']['form_title'] ?? '' );

		if ( null === $before ) {

			$this->insert_event_log(
				Events::WPFORMS,
				Actions::FORM_CREATE,
				array(
					'object_type' => 'wpforms',
					'object_id'   => $id,
					'message'     => sprintf( 'WPForms form "%s" created.', $title ),
					'after_data'  => $this->encode_form_data( $after ),
					'context'     => $this->get_common_context(),
				)
			);

			return;
		}

		if ( $before === $after ) {
			// Resubmitted with no actual change - e.g. the builder's
			// autosave firing on an untouched form. Nothing worth a row for.
			return;
		}

		// PM-170: extract the granular sub-resource events WP Activity Log's
		// own sensor reports (rename, confirmation/notification create/
		// update/delete, all-notifications on/off) from this same before/
		// after snapshot pair, *in addition to* the generic diff row below -
		// this bridge is the only place either side is ever available
		// together, since WPForms hands neither confirmations nor
		// notifications their own dedicated hook (unlike Gravity Forms -
		// see `ConfirmationActivityLogger`/`NotificationActivityLogger`
		// there).
		$this->log_sub_resource_changes( $before, $after, $id, $title );

		$before_json = $this->encode_form_data( $before );
		$after_json  = $this->encode_form_data( $after );

		$diff = ContentDiffer::diff( $before_json, $after_json );

		$log_data = array(
			'object_type' => 'wpforms',
			'object_id'   => $id,
			'message'     => sprintf( 'WPForms form "%s" updated.', $title ),
			'context'     => $this->get_common_context(),
		);

		if ( null !== $diff ) {
			$log_data['after_data'] = wp_json_encode( array( 'form_diff' => $diff ) );
		} else {
			$log_data['before_data'] = $before_json;
			$log_data['after_data']  = $after_json;
		}

		$this->insert_event_log( Events::WPFORMS, Actions::FORM_UPDATE, $log_data );
	}

	/**
	 * Log the granular sub-resource events buried inside one form save -
	 * see `log_form_updated()`'s call site for why this exists alongside
	 * (not instead of) the generic `FORM_UPDATE` row.
	 *
	 * @param array  $before  Form data before this save.
	 * @param array  $after   Form data after this save.
	 * @param int    $form_id Form ID.
	 * @param string $title   Form's current title (post-save).
	 * @return void
	 */
	protected function log_sub_resource_changes( array $before, array $after, int $form_id, string $title ): void {

		$before_settings = (array) ( $before['settings'] ?? array() );
		$after_settings  = (array) ( $after['settings'] ?? array() );

		$old_title = (string) ( $before_settings['form_title'] ?? '' );
		$new_title = (string) ( $after_settings['form_title'] ?? $title );

		if ( '' !== $old_title && $old_title !== $new_title ) {

			$this->insert_event_log(
				Events::WPFORMS,
				Actions::FORM_RENAME,
				array(
					'object_type' => 'wpforms',
					'object_id'   => $form_id,
					'message'     => sprintf( 'WPForms form "%1$s" renamed to "%2$s".', $old_title, $new_title ),
					'context'     => array_merge(
						$this->get_common_context(),
						array(
							'old_form_name' => $old_title,
							'new_form_name' => $new_title,
						)
					),
				)
			);
		}

		$this->log_sub_resource_group(
			(array) ( $before_settings['confirmations'] ?? array() ),
			(array) ( $after_settings['confirmations'] ?? array() ),
			$form_id,
			$title,
			'confirmation',
			Actions::CONFIRMATION_CREATE,
			Actions::CONFIRMATION_UPDATE,
			Actions::CONFIRMATION_DELETE
		);

		$this->log_sub_resource_group(
			(array) ( $before_settings['notifications'] ?? array() ),
			(array) ( $after_settings['notifications'] ?? array() ),
			$form_id,
			$title,
			'notification',
			Actions::NOTIFICATION_CREATE,
			Actions::NOTIFICATION_UPDATE,
			Actions::NOTIFICATION_DELETE
		);

		$was_enabled = ! empty( $before_settings['notification_enable'] );
		$is_enabled  = ! empty( $after_settings['notification_enable'] );

		if ( $was_enabled !== $is_enabled ) {

			$this->insert_event_log(
				Events::WPFORMS,
				$is_enabled ? Actions::NOTIFICATION_ACTIVATE : Actions::NOTIFICATION_DEACTIVATE,
				array(
					'object_type' => 'wpforms_notification',
					'object_id'   => $form_id,
					'severity'    => $is_enabled ? Severity::INFO : Severity::WARNING,
					'message'     => sprintf(
						$is_enabled ? 'All notifications enabled on form "%s".' : 'All notifications disabled on form "%s".',
						$title
					),
					'context'     => $this->get_common_context(),
				)
			);
		}
	}

	/**
	 * Diff one keyed sub-resource group (confirmations or notifications,
	 * both keyed by numeric string ID) between before/after, logging a
	 * create/update/delete row per changed item.
	 *
	 * @param array  $before      Sub-resource group before this save.
	 * @param array  $after       Sub-resource group after this save.
	 * @param int    $form_id     Form ID.
	 * @param string $title       Form's current title.
	 * @param string $object_type Bare object-type suffix (`confirmation`/`notification`).
	 * @param string $create_action `Actions::` constant for a new item.
	 * @param string $update_action `Actions::` constant for a changed item.
	 * @param string $delete_action `Actions::` constant for a removed item.
	 * @return void
	 */
	protected function log_sub_resource_group(
		array $before,
		array $after,
		int $form_id,
		string $title,
		string $object_type,
		string $create_action,
		string $update_action,
		string $delete_action
	): void {

		foreach ( $after as $key => $item ) {

			if ( ! array_key_exists( $key, $before ) ) {

				$this->insert_event_log(
					Events::WPFORMS,
					$create_action,
					array(
						'object_type' => 'wpforms_' . $object_type,
						'object_id'   => $form_id,
						'message'     => sprintf( 'A %1$s was added to form "%2$s".', $object_type, $title ),
						'after_data'  => wp_json_encode( $item, JSON_PRETTY_PRINT ),
						'context'     => array_merge( $this->get_common_context(), array( $object_type . '_id' => (string) $key ) ),
					)
				);

				continue;
			}

			if ( $before[ $key ] !== $item ) {

				$this->insert_event_log(
					Events::WPFORMS,
					$update_action,
					array(
						'object_type' => 'wpforms_' . $object_type,
						'object_id'   => $form_id,
						'message'     => sprintf( 'A %1$s was edited on form "%2$s".', $object_type, $title ),
						'before_data' => wp_json_encode( $before[ $key ], JSON_PRETTY_PRINT ),
						'after_data'  => wp_json_encode( $item, JSON_PRETTY_PRINT ),
						'context'     => array_merge( $this->get_common_context(), array( $object_type . '_id' => (string) $key ) ),
					)
				);
			}
		}

		foreach ( $before as $key => $item ) {

			if ( array_key_exists( $key, $after ) ) {
				continue;
			}

			$this->insert_event_log(
				Events::WPFORMS,
				$delete_action,
				array(
					'object_type' => 'wpforms_' . $object_type,
					'object_id'   => $form_id,
					'severity'    => Severity::WARNING,
					'message'     => sprintf( 'A %1$s was removed from form "%2$s".', $object_type, $title ),
					'before_data' => wp_json_encode( $item, JSON_PRETTY_PRINT ),
					'context'     => array_merge( $this->get_common_context(), array( $object_type . '_id' => (string) $key ) ),
				)
			);
		}
	}

	/**
	 * Log a form create (the primary, `wpforms_create_form`-driven signal -
	 * see `log_form_updated()`'s docblock for how this and the bridge
	 * above can both fire for the same new form).
	 *
	 * @param int   $form_id Newly-created form ID.
	 * @param array $form    The `wp_insert_post()` args just saved.
	 * @param array $data    Additional data passed to `WPForms_Form_Handler::add()`.
	 * @return void
	 */
	public function log_form_created( $form_id, $form, $data ): void {

		if ( empty( $form_id ) ) {
			return;
		}

		$title = is_array( $form ) && isset( $form['post_title'] ) ? (string) $form['post_title'] : '';

		$this->insert_event_log(
			Events::WPFORMS,
			Actions::FORM_CREATE,
			array(
				'object_type' => 'wpforms',
				'object_id'   => (int) $form_id,
				'message'     => sprintf( 'WPForms form "%s" created.', $title ),
				'context'     => $this->get_common_context(),
			)
		);
	}

	/**
	 * Log a form trash/restore.
	 *
	 * `wpforms_form_handler_update_status` is WPForms' *trash/restore*
	 * mechanism, not a separate enable/disable toggle - confirmed by
	 * reading `WPForms_Form_Handler::update_status()` itself during this
	 * ticket's implementation (its own docblock: "Status updates are used
	 * only in trash and restore actions") and its only two callers
	 * (`BulkActions::bulk_trash()`/`bulk_restore()`), both passing only
	 * `trash`/`publish`. WPForms Lite has no separate form-level enable/
	 * disable feature to log - the sprint doc's "Enabling/disabling a form"
	 * acceptance criterion is satisfied by this trash/restore pair instead,
	 * correcting that criterion's original wording to match verified
	 * behavior (see `docs/sprints/sprint-08.md`'s PM-155 notes).
	 *
	 * @param int    $form_id Form ID.
	 * @param string $status  New status - `trash` or `publish`.
	 * @return void
	 */
	public function log_form_status_changed( $form_id, $status ): void {

		if ( empty( $form_id ) || ! in_array( $status, array( 'trash', 'publish' ), true ) ) {
			return;
		}

		$post  = get_post( (int) $form_id );
		$title = $post instanceof WP_Post ? $post->post_title : '';

		if ( 'trash' === $status ) {
			$this->insert_event_log(
				Events::WPFORMS,
				Actions::FORM_TRASH,
				array(
					'object_type' => 'wpforms',
					'object_id'   => (int) $form_id,
					'severity'    => Severity::WARNING,
					'message'     => sprintf( 'WPForms form "%s" moved to trash.', $title ),
					'context'     => $this->get_common_context(),
				)
			);

			return;
		}

		$this->insert_event_log(
			Events::WPFORMS,
			Actions::FORM_RESTORE,
			array(
				'object_type' => 'wpforms',
				'object_id'   => (int) $form_id,
				'message'     => sprintf( 'WPForms form "%s" restored from trash.', $title ),
				'context'     => $this->get_common_context(),
			)
		);
	}

	/**
	 * Log form duplication.
	 *
	 * @param int   $id            Original form ID.
	 * @param int   $new_form_id   New form ID.
	 * @param array $new_form_data New form data.
	 * @return void
	 */
	public function log_form_duplicated( $id, $new_form_id, $new_form_data ): void {

		if ( empty( $new_form_id ) ) {
			return;
		}

		$title = is_array( $new_form_data ) ? (string) ( $new_form_data['settings']['form_title'] ?? '' ) : '';

		$this->insert_event_log(
			Events::WPFORMS,
			Actions::FORM_DUPLICATE,
			array(
				'object_type' => 'wpforms',
				'object_id'   => (int) $new_form_id,
				'message'     => sprintf( 'WPForms form "%s" duplicated (from form #%d).', $title, (int) $id ),
				'context'     => array_merge(
					$this->get_common_context(),
					array( 'source_form_id' => (int) $id )
				),
			)
		);
	}

	/**
	 * Snapshot a form/template just before WordPress permanently deletes
	 * it - the only point at which a pre-delete snapshot is possible at
	 * all, since no WPForms-native "before delete" hook exists (confirmed
	 * by this sprint's pre-ticketing source read).
	 *
	 * @param int $post_id Post ID about to be deleted.
	 * @return void
	 */
	public function capture_form_before_delete( $post_id ): void {

		$post_id = (int) $post_id;
		$post    = get_post( $post_id );

		if ( ! $post instanceof WP_Post || ! in_array( $post->post_type, self::FORM_POST_TYPES, true ) ) {
			return;
		}

		self::$pending_deletes[ $post_id ] = array(
			'title' => $post->post_title,
			'data'  => (array) wpforms_decode( $post->post_content ),
		);
	}

	/**
	 * Log form deletion, using the `before_delete_post` snapshot above -
	 * `wpforms_delete_form` itself only confirms the batch succeeded and
	 * hands back the ID list, not the deleted content.
	 *
	 * @param array $ids Deleted form IDs.
	 * @return void
	 */
	public function log_forms_deleted( $ids ): void {

		foreach ( (array) $ids as $id ) {

			$id = (int) $id;

			$snapshot = self::$pending_deletes[ $id ] ?? null;

			unset( self::$pending_deletes[ $id ] );

			if ( null === $snapshot ) {
				continue;
			}

			$this->insert_event_log(
				Events::WPFORMS,
				Actions::FORM_DELETE,
				array(
					'object_type' => 'wpforms',
					'object_id'   => $id,
					'severity'    => Severity::WARNING,
					'message'     => sprintf( 'WPForms form "%s" deleted.', $snapshot['title'] ),
					'before_data' => $this->encode_form_data( $snapshot['data'] ),
					'context'     => $this->get_common_context(),
				)
			);
		}
	}

	/**
	 * Encode form data for diffing/storage.
	 *
	 * `ContentDiffer` diffs line-by-line - the default `wp_json_encode()`
	 * produces a single-line string, which would make every form edit look
	 * like "the whole line changed" and defeat diffing entirely (found
	 * during PM-154's own verification, same fix applied here from the
	 * start rather than rediscovered). Pretty-printing gives each setting
	 * its own line, so a one-field edit diffs down to just that line.
	 *
	 * @param array $data Form data (already decoded via `wpforms_decode()`).
	 * @return string
	 */
	protected function encode_form_data( array $data ): string {

		return (string) wp_json_encode( $data, JSON_PRETTY_PRINT );
	}
}
