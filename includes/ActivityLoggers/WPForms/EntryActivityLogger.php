<?php // phpcs:ignore WordPress.Files.FileName.NotHyphenatedLowercase

namespace Pastmark\ActivityLoggers\WPForms;

use Pastmark\ActivityLoggers\AbstractLogger;
use Pastmark\Constants\Actions;
use Pastmark\Constants\Events;
use Pastmark\Constants\Severity;

defined( 'ABSPATH' ) || exit;

/**
 * WPForms entry-submission and notification-failure activity logger
 * (PM-155).
 *
 * **Notification-failure detection is best-effort, by design, not a bug to
 * fix later.** Confirmed by this sprint's pre-ticketing source read: every
 * code path that sends a WPForms notification email either fires its own
 * "after send" hook unconditionally (success or failure alike) or discards
 * `wp_mail()`'s return value outright - there is no `wpforms_*` hook that
 * distinguishes a successful send from a failed one. The only real signal
 * is WordPress core's own `wp_mail_failed` action, correlated back to the
 * submission that triggered it.
 *
 * That correlation is more precise than "the sprint's own original plan
 * (timing + recipient address guessing)" suggested, though - found while
 * implementing this ticket, not assumed from the plan. WPForms'
 * `Notifications` (`WPForms\Emails\Notifications extends Mailer`) already
 * carries `entry_id`/`form_data` as dynamic properties (set by
 * `Process::entry_email()` before every `send()` call) and fires
 * `wpforms_email_send_before` with `$this` immediately before its own
 * `wp_mail()` call - one call stack, no concurrency possible in PHP. Hooking
 * that gives an exact entry/form snapshot for whichever notification is
 * about to send, not a guess from "whatever submitted most recently." A
 * short time-window guard on read still exists as defense-in-depth (an
 * unrelated, later `wp_mail_failed` from some other plugin's mail could
 * otherwise be misattributed to a stale snapshot) - that's the one place
 * this remains a heuristic rather than a guarantee, same honesty standard
 * `AI-ACTOR-DETECTION.md` already sets for a different heuristic signal in
 * this codebase.
 */
class EntryActivityLogger extends AbstractLogger {

	/**
	 * {@inheritDoc}
	 *
	 * @var string
	 */
	protected $integration = 'wpforms';

	/**
	 * How long (in seconds) a captured notification-send context stays
	 * eligible to be matched against a later `wp_mail_failed`. Generous
	 * enough to cover WPForms' optional async-mailer path (a separate
	 * request), tight enough that an unrelated failure long afterward
	 * doesn't get misattributed.
	 */
	const CORRELATION_WINDOW_SECONDS = 30;

	/**
	 * The most recent notification-send context captured from
	 * `wpforms_email_send_before`, or `null` if none is currently pending
	 * (or it was already consumed by a match).
	 *
	 * @var array{entry_id: int, form_id: int, form_title: string, time: int}|null
	 */
	protected static $last_notification_context = null;

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
	 * Event-group registration (the `wpforms` group both WPForms loggers'
	 * actions live under) is owned by `FormActivityLogger::register_events()`
	 * - see that method's docblock for why only one of the two registers it.
	 *
	 * @return void
	 */
	protected function register_hooks(): void {

		add_action( 'wpforms_process_complete', $this->guarded( array( $this, 'log_entry_created' ) ), 10, 4 );

		add_action( 'wpforms_email_send_before', $this->guarded( array( $this, 'capture_notification_context' ) ), 10, 1 );

		add_action( 'wp_mail_failed', $this->guarded( array( $this, 'log_notification_failed' ) ), 10, 1 );
	}

	/**
	 * Log a genuine front-end form submission.
	 *
	 * @param array $fields    Processed field data, keyed by field ID.
	 * @param array $entry     Raw `$_POST` submission data (not used - `$fields` is already the clean, structured shape).
	 * @param array $form_data Form data and settings.
	 * @param int   $entry_id  Entry ID.
	 * @return void
	 */
	public function log_entry_created( $fields, $entry, $form_data, $entry_id ): void {

		if ( empty( $entry_id ) ) {
			return;
		}

		// `$form_data['id']` is NOT reliably present here - confirmed live
		// during this ticket's verification. WPForms' own `wpforms_decode()`
		// of a form's stored `post_content` never embeds `id` by default;
		// only a handful of code paths (e.g. `duplicate()`) explicitly write
		// it in before saving. `$entry_id` (this hook's own 4th argument,
		// always present) is the reliable identifier - `form_id` below is
		// best-effort context, not something to gate logging on.
		$title = (string) ( $form_data['settings']['form_title'] ?? '' );

		$this->insert_event_log(
			Events::WPFORMS,
			Actions::ENTRY_CREATE,
			array(
				'object_type' => 'wpforms_entry',
				'object_id'   => (int) $entry_id,
				'message'     => sprintf( 'New entry submitted for form "%s".', $title ),
				'after_data'  => wp_json_encode( $this->flatten_fields( (array) $fields ) ),
				'context'     => array_merge(
					$this->get_common_context(),
					array( 'form_id' => (int) ( $form_data['id'] ?? 0 ) )
				),
			)
		);
	}

	/**
	 * Reshape WPForms' own `$fields` (an array keyed by numeric field ID,
	 * each a `{id, name, value, type, ...}` sub-array) into a flat
	 * `{field_name => value}` map.
	 *
	 * This isn't just presentation - it's what makes masking (PM-135) able
	 * to work at all here. `SensitiveDataMasker` redacts based on *array
	 * key* names (`password`, `api_key`, ...); WPForms' native shape never
	 * puts the field's own label in a key position (it's a nested `name`
	 * *value*, under numeric keys), so a field literally labeled "Password"
	 * would sail through unmasked if logged as-is. Using the field's own
	 * (sanitized) name as the array key is what lets a `password`/`api_key`
	 * -labeled field get caught by the existing key-based masking - verified
	 * live during this ticket, not assumed.
	 *
	 * @param array $fields Raw `$fields` array from `wpforms_process_complete`.
	 * @return array<string, mixed>
	 */
	protected function flatten_fields( array $fields ): array {

		$flat = array();

		foreach ( $fields as $field ) {

			if ( ! is_array( $field ) || ! isset( $field['name'] ) || '' === trim( (string) $field['name'] ) ) {
				continue;
			}

			$key = sanitize_key( str_replace( ' ', '_', (string) $field['name'] ) );

			if ( '' === $key ) {
				$key = 'field_' . ( $field['id'] ?? count( $flat ) );
			}

			$flat[ $key ] = $field['value'] ?? '';
		}

		return $flat;
	}

	/**
	 * Snapshot which entry/form the notification about to be sent belongs
	 * to, immediately before WPForms' own `wp_mail()` call - see class
	 * docblock for why this hook point gives an exact match, not a guess.
	 *
	 * @param object $notifications The `WPForms\Emails\Notifications` instance about to send.
	 * @return void
	 */
	public function capture_notification_context( $notifications ): void {

		if ( ! is_object( $notifications ) ) {
			return;
		}

		$entry_id  = isset( $notifications->entry_id ) ? (int) $notifications->entry_id : 0;
		$form_data = isset( $notifications->form_data ) ? (array) $notifications->form_data : array();

		if ( $entry_id <= 0 ) {
			return;
		}

		// `form_data['id']` isn't reliably present (see `log_entry_created()`'s
		// docblock) - `entry_id` alone is enough to correlate a later
		// `wp_mail_failed` back to this submission; `form_id`/`form_title`
		// below are still captured when available, just not required.

		self::$last_notification_context = array(
			'entry_id'   => $entry_id,
			'form_id'    => (int) ( $form_data['id'] ?? 0 ),
			'form_title' => (string) ( $form_data['settings']['form_title'] ?? '' ),
			'time'       => time(),
		);
	}

	/**
	 * Log a notification-send failure, if it correlates to a recently
	 * captured WPForms notification-send context.
	 *
	 * @param \WP_Error $wp_error Error object `wp_mail()` failed with.
	 * @return void
	 */
	public function log_notification_failed( $wp_error ): void {

		if ( null === self::$last_notification_context ) {
			return;
		}

		$context = self::$last_notification_context;

		if ( ( time() - $context['time'] ) > self::CORRELATION_WINDOW_SECONDS ) {
			return;
		}

		// Consumed on first match so a second, unrelated `wp_mail_failed`
		// later in the same request can't be attributed to the same
		// already-used snapshot.
		self::$last_notification_context = null;

		$error_data = ( $wp_error instanceof \WP_Error ) ? (array) $wp_error->get_error_data( 'wp_mail_failed' ) : array();

		$this->insert_event_log(
			Events::WPFORMS,
			Actions::NOTIFICATION_FAILED,
			array(
				'object_type' => 'wpforms_entry',
				'object_id'   => $context['entry_id'],
				'severity'    => Severity::WARNING,
				'message'     => sprintf(
					'Notification email failed to send for form "%s" (entry #%d).',
					$context['form_title'],
					$context['entry_id']
				),
				'context'     => array_merge(
					$this->get_common_context(),
					array(
						'form_id'       => $context['form_id'],
						'error_message' => ( $wp_error instanceof \WP_Error ) ? $wp_error->get_error_message() : '',
						'recipient'     => $error_data['to'] ?? '',
					)
				),
			)
		);
	}
}
