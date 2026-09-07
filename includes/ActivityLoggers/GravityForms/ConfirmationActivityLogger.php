<?php // phpcs:ignore WordPress.Files.FileName.NotHyphenatedLowercase

namespace Pastmark\ActivityLoggers\GravityForms;

use Pastmark\ActivityLoggers\AbstractLogger;
use Pastmark\Constants\Actions;
use Pastmark\Constants\Events;
use Pastmark\Constants\Severity;

defined( 'ABSPATH' ) || exit;

/**
 * Gravity Forms confirmation activity logger (PM-169).
 *
 * Closes part of the gap between this integration's original Sprint 8 scope
 * and WP Activity Log's own Gravity Forms sensor - its alerts 5705
 * (confirmation created/modified/deleted) and 5708 (confirmation activated/
 * deactivated).
 *
 * Unlike WP Activity Log's own sensor (which infers create-vs-update by
 * comparing a `gform_form_post_get_meta`-captured snapshot), Gravity Forms'
 * `gform_pre_confirmation_save` filter already hands over `$is_new_confirmation`
 * directly as its third argument - confirmed by reading
 * `GF_Confirmation::maybe_process_confirmation_data()` - so no snapshot/diff
 * inference is needed here at all, matching how `FormActivityLogger` already
 * prefers `gform_after_save_form`'s own `$is_new` over inferring it.
 *
 * Activation/deactivation isn't its own hook (Gravity Forms has one for
 * notifications - see `NotificationActivityLogger` - but not confirmations):
 * a confirmation's `isActive` flag is just one more field on the same
 * `gform_pre_confirmation_save` payload, so an activate/deactivate is
 * detected here by comparing it against the form's *current* stored
 * confirmation (available as `$form['confirmations'][$id]` at the moment
 * this filter fires, since the merge back into `$form` happens after) rather
 * than treated as a generic edit.
 */
class ConfirmationActivityLogger extends AbstractLogger {

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

		add_filter( 'gform_pre_confirmation_save', $this->guarded( array( $this, 'log_confirmation_saved' ) ), 10, 3 );

		add_action( 'gform_pre_confirmation_deleted', $this->guarded( array( $this, 'log_confirmation_deleted' ) ), 10, 2 );
	}

	/**
	 * Log a confirmation create/update, or an activate/deactivate when only
	 * `isActive` changed.
	 *
	 * @param array $confirmation        The confirmation being saved.
	 * @param array $form                The form it belongs to.
	 * @param bool  $is_new_confirmation Whether this is a brand-new confirmation.
	 * @return array The confirmation, unchanged - this is a filter.
	 */
	public function log_confirmation_saved( $confirmation, $form, $is_new_confirmation ) {

		if ( ! is_array( $confirmation ) || ! is_array( $form ) || empty( $confirmation['id'] ) ) {
			return $confirmation;
		}

		$form_id   = isset( $form['id'] ) ? (int) $form['id'] : 0;
		$form_name = isset( $form['title'] ) ? (string) $form['title'] : '';
		$name      = isset( $confirmation['name'] ) && '' !== trim( (string) $confirmation['name'] )
			? (string) $confirmation['name']
			: __( '(untitled)', 'pastmark' );

		if ( $is_new_confirmation ) {

			$this->insert_event_log(
				Events::GRAVITYFORMS,
				Actions::CONFIRMATION_CREATE,
				array(
					'object_type' => 'gravityforms_confirmation',
					'object_id'   => $form_id,
					'message'     => sprintf( 'Confirmation "%1$s" created on form "%2$s".', $name, $form_name ),
					'after_data'  => wp_json_encode( $confirmation, JSON_PRETTY_PRINT ),
					'context'     => array_merge( $this->get_common_context(), array( 'confirmation_id' => (string) $confirmation['id'] ) ),
				)
			);

			return $confirmation;
		}

		$existing = isset( $form['confirmations'][ $confirmation['id'] ] ) ? $form['confirmations'][ $confirmation['id'] ] : null;

		$was_active = ! is_array( $existing ) || false !== ( $existing['isActive'] ?? true );
		$is_active  = false !== ( $confirmation['isActive'] ?? true );

		if ( is_array( $existing ) && $was_active !== $is_active ) {

			$this->insert_event_log(
				Events::GRAVITYFORMS,
				$is_active ? Actions::CONFIRMATION_ACTIVATE : Actions::CONFIRMATION_DEACTIVATE,
				array(
					'object_type' => 'gravityforms_confirmation',
					'object_id'   => $form_id,
					'severity'    => $is_active ? Severity::INFO : Severity::WARNING,
					'message'     => sprintf(
						$is_active ? 'Confirmation "%1$s" activated on form "%2$s".' : 'Confirmation "%1$s" deactivated on form "%2$s".',
						$name,
						$form_name
					),
					'context'     => array_merge( $this->get_common_context(), array( 'confirmation_id' => (string) $confirmation['id'] ) ),
				)
			);

			return $confirmation;
		}

		$this->insert_event_log(
			Events::GRAVITYFORMS,
			Actions::CONFIRMATION_UPDATE,
			array(
				'object_type' => 'gravityforms_confirmation',
				'object_id'   => $form_id,
				'message'     => sprintf( 'Confirmation "%1$s" edited on form "%2$s".', $name, $form_name ),
				'before_data' => is_array( $existing ) ? wp_json_encode( $existing, JSON_PRETTY_PRINT ) : null,
				'after_data'  => wp_json_encode( $confirmation, JSON_PRETTY_PRINT ),
				'context'     => array_merge( $this->get_common_context(), array( 'confirmation_id' => (string) $confirmation['id'] ) ),
			)
		);

		return $confirmation;
	}

	/**
	 * Log a confirmation deletion.
	 *
	 * @param array $confirmation The deleted confirmation.
	 * @param array $form         The form it belonged to.
	 * @return array The confirmation, unchanged - this is a filter-shaped action.
	 */
	public function log_confirmation_deleted( $confirmation, $form ) {

		if ( ! is_array( $confirmation ) || ! is_array( $form ) ) {
			return $confirmation;
		}

		$form_name = isset( $form['title'] ) ? (string) $form['title'] : '';
		$name      = isset( $confirmation['name'] ) && '' !== trim( (string) $confirmation['name'] )
			? (string) $confirmation['name']
			: __( '(untitled)', 'pastmark' );

		$this->insert_event_log(
			Events::GRAVITYFORMS,
			Actions::CONFIRMATION_DELETE,
			array(
				'object_type' => 'gravityforms_confirmation',
				'object_id'   => isset( $form['id'] ) ? (int) $form['id'] : 0,
				'severity'    => Severity::WARNING,
				'message'     => sprintf( 'Confirmation "%1$s" deleted from form "%2$s".', $name, $form_name ),
				'before_data' => wp_json_encode( $confirmation, JSON_PRETTY_PRINT ),
				'context'     => $this->get_common_context(),
			)
		);

		return $confirmation;
	}
}
