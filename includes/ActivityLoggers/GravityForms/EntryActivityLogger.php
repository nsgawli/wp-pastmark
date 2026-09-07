<?php // phpcs:ignore WordPress.Files.FileName.NotHyphenatedLowercase

namespace Pastmark\ActivityLoggers\GravityForms;

use Pastmark\ActivityLoggers\AbstractLogger;
use Pastmark\Constants\Actions;
use Pastmark\Constants\Events;
use Pastmark\Constants\Severity;

defined( 'ABSPATH' ) || exit;

/**
 * Gravity Forms entry-submission and notification-failure activity logger
 * (PM-156).
 *
 * **Notification-failure detection is a real, reliable feature here, not
 * best-effort** - the one place this sprint's three forms/fields
 * integrations genuinely aren't symmetric. Confirmed by reading
 * `GFCommon::send_email()` (`common.php`): it captures `wp_mail()`'s own
 * return value into `$is_success` and hands it straight to
 * `do_action( 'gform_after_email', $is_success, ..., $entry, $cc )` - a
 * real, unconditional success/failure signal with the actual `$entry`
 * attached, unlike WPForms (PM-155, no such signal exists at all) or ACF
 * (no notification concept). `false === $is_success || is_wp_error(
 * $is_success )` is all that's needed; no timing/recipient correlation
 * heuristic required.
 */
class EntryActivityLogger extends AbstractLogger {

	/**
	 * {@inheritDoc}
	 *
	 * @var string
	 */
	protected $integration = 'gravityforms';

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
	 * Event-group registration (the `gravityforms` group both loggers'
	 * actions live under) is owned by `FormActivityLogger::register_events()`
	 * - see that method's docblock for why only one of the two registers it.
	 *
	 * @return void
	 */
	protected function register_hooks(): void {

		add_action( 'gform_entry_created', $this->guarded( array( $this, 'log_entry_created' ) ), 10, 2 );

		// `gform_after_email` passes 14 args total; `$entry` is the 12th.
		// Declaring all 12 leading params by name (rather than a smaller
		// accepted_args count) avoids the exact ArgumentCountError class of
		// bug PM-155 hit twice with WPForms' own hooks.
		add_action( 'gform_after_email', $this->guarded( array( $this, 'log_notification_failed' ) ), 10, 12 );

		// --- PM-169 granular parity additions - see class docblock. ---

		// Single hook for star/unstar, read/unread, and status transitions
		// (trash/restore/spam) - `GFFormsModel::update_entry_property()`
		// fires this one action for every property it updates, dispatching
		// by `$property_name` below rather than needing a hook per property.
		add_action( 'gform_post_update_entry_property', $this->guarded( array( $this, 'log_entry_property_updated' ) ), 10, 4 );

		// Fires *before* the row is actually deleted (confirmed by reading
		// `GFFormsModel::delete_entry()`), so `GFAPI::get_entry()` is still
		// available here to capture what's being removed - no snapshot/
		// transient dance needed the way `FormActivityLogger`'s delete path
		// requires (that one only has a *before* hook paired with a
		// separate *after* hook).
		add_action( 'gform_delete_entry', $this->guarded( array( $this, 'log_entry_deleted' ) ), 10, 1 );

		add_action( 'gform_post_export_entries', $this->guarded( array( $this, 'log_entries_exported' ) ), 10, 5 );

		add_action( 'gform_post_note_added', $this->guarded( array( $this, 'log_entry_note_added' ) ), 10, 7 );

		add_action( 'gform_pre_note_deleted', $this->guarded( array( $this, 'log_entry_note_deleted' ) ), 10, 2 );

		// A real, dedicated post-admin-edit hook (`entry_detail.php`'s own
		// `lead_detail_edit()`) - reliably distinguishes an admin editing an
		// entry's field values from every other property update above,
		// unlike WP Activity Log's own equivalent (which infers this case by
		// sniffing `$_POST['gforms_save_entry']`/`$_POST['name']` inside its
		// `gform_post_update_entry_property` callback).
		add_action( 'gform_after_update_entry', $this->guarded( array( $this, 'log_entry_edited' ) ), 10, 3 );
	}

	/**
	 * Log a genuine front-end form submission.
	 *
	 * @param array $entry The Entry object.
	 * @param array $form  The Form object.
	 * @return void
	 */
	public function log_entry_created( $entry, $form ): void {

		if ( ! is_array( $entry ) || empty( $entry['id'] ) ) {
			return;
		}

		$title = is_array( $form ) ? (string) ( $form['title'] ?? '' ) : '';

		$this->insert_event_log(
			Events::GRAVITYFORMS,
			Actions::ENTRY_CREATE,
			array(
				'object_type' => 'gravityforms_entry',
				'object_id'   => (int) $entry['id'],
				'message'     => sprintf( 'New entry submitted for form "%s".', $title ),
				'after_data'  => wp_json_encode( $this->flatten_fields( $entry, is_array( $form ) ? $form : array() ) ),
				'context'     => array_merge(
					$this->get_common_context(),
					array( 'form_id' => (int) ( $entry['form_id'] ?? 0 ) )
				),
			)
		);
	}

	/**
	 * Dispatch an entry property update to the right specific event -
	 * star/unstar, read/unread, or a status transition (trash/restore/spam).
	 * Any other property (e.g. `payment_status`) is intentionally ignored;
	 * an admin's actual field-value edits are covered separately by
	 * `log_entry_edited()`, via a real dedicated hook rather than inference.
	 *
	 * @param int    $entry_id       Entry ID.
	 * @param string $property_name  Property that was updated.
	 * @param string $property_value New value.
	 * @param string $previous_value Previous value.
	 * @return void
	 */
	public function log_entry_property_updated( $entry_id, $property_name, $property_value, $previous_value ): void {

		switch ( $property_name ) {

			case 'is_starred':
				$this->log_entry_event(
					(int) $entry_id,
					$property_value ? Actions::ENTRY_STAR : Actions::ENTRY_UNSTAR,
					$property_value ? 'starred' : 'unstarred'
				);
				break;

			case 'is_read':
				$this->log_entry_event(
					(int) $entry_id,
					$property_value ? Actions::ENTRY_READ : Actions::ENTRY_UNREAD,
					$property_value ? 'marked read' : 'marked unread'
				);
				break;

			case 'status':
				if ( 'trash' === $property_value ) {
					$this->log_entry_event( (int) $entry_id, Actions::ENTRY_TRASH, 'moved to trash', Severity::WARNING );
				} elseif ( 'active' === $property_value && 'trash' === $previous_value ) {
					$this->log_entry_event( (int) $entry_id, Actions::ENTRY_RESTORE, 'restored from trash' );
				}
				break;
		}
	}

	/**
	 * Shared implementation for the simple entry-property events above -
	 * they all just need the entry's own current data and a verb.
	 *
	 * @param int    $entry_id Entry ID.
	 * @param string $action   `Actions::` constant to log.
	 * @param string $verb     Verb for the log message (e.g. "starred").
	 * @param string $severity Severity - defaults to `Severity::INFO`.
	 * @return void
	 */
	protected function log_entry_event( int $entry_id, string $action, string $verb, string $severity = Severity::INFO ): void {

		$entry = class_exists( 'GFAPI' ) ? \GFAPI::get_entry( $entry_id ) : null;

		if ( ! is_array( $entry ) ) {
			return;
		}

		$form_id = (int) ( $entry['form_id'] ?? 0 );

		$this->insert_event_log(
			Events::GRAVITYFORMS,
			$action,
			array(
				'object_type' => 'gravityforms_entry',
				'object_id'   => $entry_id,
				'severity'    => $severity,
				'message'     => sprintf( 'Entry #%1$d %2$s.', $entry_id, $verb ),
				'context'     => array_merge( $this->get_common_context(), array( 'form_id' => $form_id ) ),
			)
		);
	}

	/**
	 * Log a permanent entry deletion - see `register_hooks()`'s comment on
	 * why no pre-delete snapshot/transient is needed here.
	 *
	 * @param int $entry_id Entry ID about to be deleted.
	 * @return void
	 */
	public function log_entry_deleted( $entry_id ): void {

		$entry_id = (int) $entry_id;
		$entry    = class_exists( 'GFAPI' ) ? \GFAPI::get_entry( $entry_id ) : null;

		if ( ! is_array( $entry ) ) {
			return;
		}

		$this->insert_event_log(
			Events::GRAVITYFORMS,
			Actions::ENTRY_DELETE,
			array(
				'object_type' => 'gravityforms_entry',
				'object_id'   => $entry_id,
				'severity'    => Severity::WARNING,
				'message'     => sprintf( 'Entry #%d permanently deleted.', $entry_id ),
				'before_data' => wp_json_encode( $this->flatten_fields( $entry, $this->get_form_for_entry( $entry ) ) ),
				'context'     => array_merge(
					$this->get_common_context(),
					array( 'form_id' => (int) ( $entry['form_id'] ?? 0 ) )
				),
			)
		);
	}

	/**
	 * Log an entries export.
	 *
	 * @param array  $form       Form the entries were exported from.
	 * @param string $start_date Export date-range start.
	 * @param string $end_date   Export date-range end.
	 * @param array  $fields     Fields included in the export.
	 * @param string $export_id  Unique export ID.
	 * @return void
	 */
	public function log_entries_exported( $form, $start_date, $end_date, $fields, $export_id ): void {

		if ( ! is_array( $form ) || empty( $form['id'] ) ) {
			return;
		}

		$title = isset( $form['title'] ) ? (string) $form['title'] : '';

		$this->insert_event_log(
			Events::GRAVITYFORMS,
			Actions::ENTRY_EXPORT,
			array(
				'object_type' => 'gravityforms_form',
				'object_id'   => (int) $form['id'],
				'message'     => sprintf( 'Entries exported from form "%s".', $title ),
				'context'     => array_merge(
					$this->get_common_context(),
					array(
						'start_date' => (string) $start_date,
						'end_date'   => (string) $end_date,
					)
				),
			)
		);
	}

	/**
	 * Log an entry note being added - restricted to genuine user-authored
	 * notes (`note_type === 'user'`), same restriction WP Activity Log's own
	 * sensor applies, so an add-on's or Gravity Forms' own automated system
	 * note doesn't get logged as if a person wrote it.
	 *
	 * @param int    $note_id   Inserted note ID.
	 * @param int    $entry_id  Entry ID.
	 * @param int    $user_id   User ID.
	 * @param string $user_name User display name.
	 * @param string $note      Note content.
	 * @param string $note_type Note type (`user`, `note`, ...).
	 * @param string $sub_type  Note sub-type.
	 * @return void
	 */
	public function log_entry_note_added( $note_id, $entry_id, $user_id, $user_name, $note, $note_type, $sub_type = '' ): void {

		if ( 'user' !== $note_type ) {
			return;
		}

		$entry_id = (int) $entry_id;
		$entry    = class_exists( 'GFAPI' ) ? \GFAPI::get_entry( $entry_id ) : null;

		if ( ! is_array( $entry ) ) {
			return;
		}

		$this->insert_event_log(
			Events::GRAVITYFORMS,
			Actions::ENTRY_NOTE_ADD,
			array(
				'object_type' => 'gravityforms_entry',
				'object_id'   => $entry_id,
				'message'     => sprintf( 'Note added to entry #%d.', $entry_id ),
				'after_data'  => (string) $note,
				'context'     => array_merge(
					$this->get_common_context(),
					array( 'form_id' => (int) ( $entry['form_id'] ?? 0 ) )
				),
			)
		);
	}

	/**
	 * Log an entry note being deleted - fires before deletion, so the note's
	 * own content is still fetchable via `GFAPI::get_note()`.
	 *
	 * @param int $note_id  Note ID about to be deleted.
	 * @param int $entry_id Entry ID.
	 * @return void
	 */
	public function log_entry_note_deleted( $note_id, $entry_id ): void {

		$entry_id = (int) $entry_id;
		$entry    = class_exists( 'GFAPI' ) ? \GFAPI::get_entry( $entry_id ) : null;
		$note     = class_exists( 'GFAPI' ) ? \GFAPI::get_note( (int) $note_id ) : null;

		if ( ! is_array( $entry ) ) {
			return;
		}

		$this->insert_event_log(
			Events::GRAVITYFORMS,
			Actions::ENTRY_NOTE_DELETE,
			array(
				'object_type' => 'gravityforms_entry',
				'object_id'   => $entry_id,
				'severity'    => Severity::WARNING,
				'message'     => sprintf( 'Note deleted from entry #%d.', $entry_id ),
				'before_data' => is_object( $note ) ? (string) ( $note->value ?? '' ) : null,
				'context'     => array_merge(
					$this->get_common_context(),
					array( 'form_id' => (int) ( $entry['form_id'] ?? 0 ) )
				),
			)
		);
	}

	/**
	 * Log an admin editing an entry's own field values from the Entry Detail
	 * screen - see `register_hooks()`'s comment on why `gform_after_update_entry`
	 * is used over inferring this from `gform_post_update_entry_property`.
	 *
	 * @param array $form           The form the entry belongs to.
	 * @param int   $entry_id       Entry ID.
	 * @param array $original_entry The entry's data before this edit.
	 * @return void
	 */
	public function log_entry_edited( $form, $entry_id, $original_entry ): void {

		$entry_id = (int) $entry_id;
		$entry    = class_exists( 'GFAPI' ) ? \GFAPI::get_entry( $entry_id ) : null;

		if ( ! is_array( $entry ) ) {
			return;
		}

		$form      = is_array( $form ) ? $form : array();
		$form_name = isset( $form['title'] ) ? (string) $form['title'] : '';

		$before = is_array( $original_entry ) ? $this->flatten_fields( $original_entry, $form ) : array();
		$after  = $this->flatten_fields( $entry, $form );

		$this->insert_event_log(
			Events::GRAVITYFORMS,
			Actions::ENTRY_EDIT,
			array(
				'object_type' => 'gravityforms_entry',
				'object_id'   => $entry_id,
				'message'     => sprintf( 'Entry #%1$d edited on form "%2$s".', $entry_id, $form_name ),
				'before_data' => wp_json_encode( $before ),
				'after_data'  => wp_json_encode( $after ),
				'context'     => array_merge(
					$this->get_common_context(),
					array( 'form_id' => (int) ( $entry['form_id'] ?? 0 ) )
				),
			)
		);
	}

	/**
	 * Best-effort form lookup for an entry, used only where a full `$form`
	 * (for `flatten_fields()`'s field-label cross-reference) isn't already
	 * on hand from the triggering hook itself.
	 *
	 * @param array $entry Entry array.
	 * @return array
	 */
	protected function get_form_for_entry( array $entry ): array {

		if ( empty( $entry['form_id'] ) || ! class_exists( 'GFAPI' ) ) {
			return array();
		}

		$form = \GFAPI::get_form( (int) $entry['form_id'] );

		return is_array( $form ) ? $form : array();
	}

	/**
	 * Reshape a Gravity Forms entry (keyed by numeric field ID, e.g.
	 * `$entry['1']`, `$entry['2.3']` for multi-input fields) into a flat
	 * `{field_label => value}` map, cross-referencing `$form['fields']` for
	 * each field's own label.
	 *
	 * Same reason as `WPForms\EntryActivityLogger::flatten_fields()`
	 * (PM-155): `SensitiveDataMasker` redacts based on *array key* names,
	 * and a numeric field ID is never going to match `password`/`api_key`
	 * - the field's own label has to land in key position for masking to
	 * have anything to catch. Gravity Forms conveniently already
	 * re-hydrates plaintext password values into `$entry` right before
	 * firing `gform_entry_created` (`GF_Field_Password::hydrate_passwords()`,
	 * confirmed in `form_display.php`) specifically so integrations like
	 * this one can see them - making masking here the only thing standing
	 * between that plaintext and storage, not a hypothetical concern.
	 *
	 * @param array $entry Raw entry array.
	 * @param array $form  Form array (for field labels/types).
	 * @return array<string, mixed>
	 */
	protected function flatten_fields( array $entry, array $form ): array {

		$flat = array();

		if ( ! class_exists( 'GFFormsModel' ) || empty( $form['fields'] ) || ! is_array( $form['fields'] ) ) {
			return $flat;
		}

		foreach ( $form['fields'] as $field ) {

			$label = is_object( $field ) ? (string) ( $field->label ?? '' ) : '';

			if ( '' === trim( $label ) ) {
				continue;
			}

			$key = sanitize_key( str_replace( ' ', '_', $label ) );

			if ( '' === $key ) {
				$key = 'field_' . ( is_object( $field ) ? ( $field->id ?? count( $flat ) ) : count( $flat ) );
			}

			$flat[ $key ] = \GFFormsModel::get_lead_field_value( $entry, $field );
		}

		return $flat;
	}

	/**
	 * Log a notification-send failure - `$is_success` is a real,
	 * unconditional send-result signal here (see class docblock), not a
	 * correlation guess.
	 *
	 * @param bool|\WP_Error $is_success     Whether `wp_mail()` succeeded.
	 * @param string         $to             Recipient address.
	 * @param string         $subject        Subject line.
	 * @param string         $message        Message body.
	 * @param array          $headers        Email headers.
	 * @param string         $attachments    Email attachments.
	 * @param string         $message_format Format of the email.
	 * @param string         $from           Sender address.
	 * @param string         $from_name      Sender display name.
	 * @param string         $bcc            BCC recipients.
	 * @param string         $reply_to       Reply-to address.
	 * @param array          $entry          Entry object associated with the sent email.
	 * @return void
	 */
	public function log_notification_failed(
		$is_success,
		$to,
		$subject,
		$message,
		$headers,
		$attachments,
		$message_format,
		$from,
		$from_name,
		$bcc,
		$reply_to,
		$entry
	): void {

		$failed = ( false === $is_success ) || is_wp_error( $is_success );

		if ( ! $failed || ! is_array( $entry ) || empty( $entry['id'] ) ) {
			return;
		}

		$error_message = is_wp_error( $is_success ) ? $is_success->get_error_message() : '';

		$this->insert_event_log(
			Events::GRAVITYFORMS,
			Actions::NOTIFICATION_FAILED,
			array(
				'object_type' => 'gravityforms_entry',
				'object_id'   => (int) $entry['id'],
				'severity'    => Severity::WARNING,
				'message'     => sprintf( 'Notification email failed to send for entry #%d.', (int) $entry['id'] ),
				'context'     => array_merge(
					$this->get_common_context(),
					array(
						'form_id'       => (int) ( $entry['form_id'] ?? 0 ),
						'recipient'     => (string) $to,
						'error_message' => $error_message,
					)
				),
			)
		);
	}
}
