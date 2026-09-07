<?php // phpcs:ignore WordPress.Files.FileName.NotHyphenatedLowercase

namespace Pastmark\ActivityLoggers\GravityForms;

use Pastmark\ActivityLoggers\AbstractLogger;
use Pastmark\Constants\Actions;
use Pastmark\Constants\Events;
use Pastmark\Constants\Severity;

defined( 'ABSPATH' ) || exit;

/**
 * Gravity Forms notification activity logger (PM-169).
 *
 * Closes part of the gap between this integration's original Sprint 8 scope
 * and WP Activity Log's own Gravity Forms sensor - its alerts 5706
 * (notification created/modified/deleted) and 5707 (notification activated/
 * deactivated).
 *
 * Deliberately separate from `EntryActivityLogger::log_notification_failed()`
 * - that's about a notification *email send* failing at delivery time
 * (`gform_after_email`); this class is about the notification *definition*
 * itself being created, edited, deleted, or toggled in the form editor.
 * Unrelated concepts that happen to share the word "notification".
 *
 * `gform_pre_notification_save` hands over `$is_new_notification` directly
 * (confirmed in `GF_Notification::maybe_process_notification_data()`), and
 * `gform_pre_notification_activated`/`_deactivated` are real, dedicated
 * hooks (`GFFormsModel::update_notification_active()`) - unlike
 * `ConfirmationActivityLogger`, no isActive-comparison inference is needed
 * here at all.
 */
class NotificationActivityLogger extends AbstractLogger {

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
	 * Event-group registration is owned by `FormActivityLogger` - see that
	 * class's `register_events()` docblock.
	 *
	 * @return void
	 */
	protected function register_hooks(): void {

		add_filter( 'gform_pre_notification_save', $this->guarded( array( $this, 'log_notification_saved' ) ), 10, 3 );

		add_action( 'gform_pre_notification_deleted', $this->guarded( array( $this, 'log_notification_deleted' ) ), 10, 2 );

		add_action( 'gform_pre_notification_activated', $this->guarded( array( $this, 'log_notification_activated' ) ), 10, 2 );

		add_action( 'gform_pre_notification_deactivated', $this->guarded( array( $this, 'log_notification_deactivated' ) ), 10, 2 );
	}

	/**
	 * Log a notification create/update.
	 *
	 * @param array $notification        The notification being saved.
	 * @param array $form                The form it belongs to.
	 * @param bool  $is_new_notification Whether this is a brand-new notification.
	 * @return array The notification, unchanged - this is a filter.
	 */
	public function log_notification_saved( $notification, $form, $is_new_notification ) {

		if ( ! is_array( $notification ) || ! is_array( $form ) || empty( $notification['id'] ) ) {
			return $notification;
		}

		$form_name = isset( $form['title'] ) ? (string) $form['title'] : '';
		$name      = isset( $notification['name'] ) && '' !== trim( (string) $notification['name'] )
			? (string) $notification['name']
			: __( '(untitled)', 'pastmark' );

		$existing = isset( $form['notifications'][ $notification['id'] ] ) ? $form['notifications'][ $notification['id'] ] : null;

		$this->insert_event_log(
			Events::GRAVITYFORMS,
			$is_new_notification ? Actions::NOTIFICATION_CREATE : Actions::NOTIFICATION_UPDATE,
			array(
				'object_type' => 'gravityforms_notification',
				'object_id'   => isset( $form['id'] ) ? (int) $form['id'] : 0,
				'message'     => sprintf(
					$is_new_notification ? 'Notification "%1$s" created on form "%2$s".' : 'Notification "%1$s" edited on form "%2$s".',
					$name,
					$form_name
				),
				'before_data' => ( ! $is_new_notification && is_array( $existing ) ) ? wp_json_encode( $existing, JSON_PRETTY_PRINT ) : null,
				'after_data'  => wp_json_encode( $notification, JSON_PRETTY_PRINT ),
				'context'     => array_merge( $this->get_common_context(), array( 'notification_id' => (string) $notification['id'] ) ),
			)
		);

		return $notification;
	}

	/**
	 * Log a notification deletion.
	 *
	 * @param array $notification The deleted notification.
	 * @param array $form         The form it belonged to.
	 * @return array The notification, unchanged - this is a filter-shaped action.
	 */
	public function log_notification_deleted( $notification, $form ) {

		if ( ! is_array( $notification ) || ! is_array( $form ) ) {
			return $notification;
		}

		$form_name = isset( $form['title'] ) ? (string) $form['title'] : '';
		$name      = isset( $notification['name'] ) && '' !== trim( (string) $notification['name'] )
			? (string) $notification['name']
			: __( '(untitled)', 'pastmark' );

		$this->insert_event_log(
			Events::GRAVITYFORMS,
			Actions::NOTIFICATION_DELETE,
			array(
				'object_type' => 'gravityforms_notification',
				'object_id'   => isset( $form['id'] ) ? (int) $form['id'] : 0,
				'severity'    => Severity::WARNING,
				'message'     => sprintf( 'Notification "%1$s" deleted from form "%2$s".', $name, $form_name ),
				'before_data' => wp_json_encode( $notification, JSON_PRETTY_PRINT ),
				'context'     => $this->get_common_context(),
			)
		);

		return $notification;
	}

	/**
	 * Log a notification activation.
	 *
	 * @param array $notification The activated notification.
	 * @param array $form         The form it belongs to.
	 * @return void
	 */
	public function log_notification_activated( $notification, $form ): void {

		$this->log_notification_active_change( $notification, $form, true );
	}

	/**
	 * Log a notification deactivation.
	 *
	 * @param array $notification The deactivated notification.
	 * @param array $form         The form it belongs to.
	 * @return void
	 */
	public function log_notification_deactivated( $notification, $form ): void {

		$this->log_notification_active_change( $notification, $form, false );
	}

	/**
	 * Shared implementation for the two activate/deactivate hooks above.
	 *
	 * @param array $notification The notification.
	 * @param array $form         The form it belongs to.
	 * @param bool  $is_active    Whether this is an activation (`true`) or
	 *                            deactivation (`false`).
	 * @return void
	 */
	protected function log_notification_active_change( $notification, $form, bool $is_active ): void {

		if ( ! is_array( $notification ) || ! is_array( $form ) ) {
			return;
		}

		$form_name = isset( $form['title'] ) ? (string) $form['title'] : '';
		$name      = isset( $notification['name'] ) && '' !== trim( (string) $notification['name'] )
			? (string) $notification['name']
			: __( '(untitled)', 'pastmark' );

		$this->insert_event_log(
			Events::GRAVITYFORMS,
			$is_active ? Actions::NOTIFICATION_ACTIVATE : Actions::NOTIFICATION_DEACTIVATE,
			array(
				'object_type' => 'gravityforms_notification',
				'object_id'   => isset( $form['id'] ) ? (int) $form['id'] : 0,
				'severity'    => $is_active ? Severity::INFO : Severity::WARNING,
				'message'     => sprintf(
					$is_active ? 'Notification "%1$s" activated on form "%2$s".' : 'Notification "%1$s" deactivated on form "%2$s".',
					$name,
					$form_name
				),
				'context'     => array_merge( $this->get_common_context(), array( 'notification_id' => (string) ( $notification['id'] ?? '' ) ) ),
			)
		);
	}
}
