<?php // phpcs:ignore WordPress.Files.FileName.NotHyphenatedLowercase

namespace Pastmark\ActivityLoggers\GravityForms;

use Pastmark\ActivityLoggers\AbstractLogger;
use Pastmark\Constants\Actions;
use Pastmark\Constants\Events;
use Pastmark\Constants\Severity;

defined( 'ABSPATH' ) || exit;

/**
 * Gravity Forms import/export and global-settings activity logger (PM-169).
 *
 * Added on real user request, comparing this integration's original Sprint 8
 * scope (`FormActivityLogger`/`EntryActivityLogger`, 8 actions) against WP
 * Activity Log's own Gravity Forms sensor (`wp-security-audit-log`'s alerts
 * 5716/5718/5719) - covers the three parts of that gap that are about the
 * plugin as a whole rather than a single form or entry: a form being
 * imported/exported, entries being exported, and a global Gravity Forms
 * setting changing.
 *
 * Only `gform_post_export_entries` (entry export) lives on
 * `EntryActivityLogger` instead - it's entry-scoped, not form-scoped, so it
 * belongs with that logger's other entry events, not here.
 */
class FormSettingsActivityLogger extends AbstractLogger {

	/**
	 * {@inheritDoc}
	 *
	 * @var string
	 */
	protected $integration = 'gravityforms';

	/**
	 * Recognized Gravity Forms global setting option names, mapped to a
	 * human-readable label - deliberately an allowlist (matching WP Activity
	 * Log's own approach in `Gravity_Forms_Sensor::event_settings_updated()`)
	 * rather than a broad `gform`/`gravityforms` substring match, so this
	 * doesn't fire for high-volume internal bookkeeping options that happen
	 * to share the prefix (`gform_email_count`, `gform_version_info`,
	 * `rg_gforms_key`, ...).
	 *
	 * @var array<string, string>
	 */
	const KNOWN_SETTINGS = array(
		'rg_gforms_disable_css'                         => 'Output CSS',
		'rg_gforms_enable_html5'                        => 'Output HTML5',
		'gform_enable_noconflict'                       => 'No-Conflict Mode',
		'rg_gforms_currency'                            => 'Currency',
		'gform_enable_background_updates'               => 'Background Updates',
		'gform_enable_toolbar_menu'                     => 'Toolbar Menu',
		'gform_enable_logging'                          => 'Logging',
		'rg_gforms_captcha_type'                        => 'Captcha Type',
		'gravityformsaddon_gravityformswebapi_settings' => 'Gravity Forms API Settings',
		'rg_gforms_enable_akismet'                      => 'Akismet Integration',
	);

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

		add_action( 'gform_forms_post_import', $this->guarded( array( $this, 'log_forms_imported' ) ), 10, 1 );

		add_filter( 'gform_form_export_filename', $this->guarded( array( $this, 'log_forms_exported' ) ), 10, 2 );

		add_action( 'updated_option', $this->guarded( array( $this, 'log_settings_changed' ) ), 10, 3 );
	}

	/**
	 * Log each form imported in one batch.
	 *
	 * @param array $forms Imported form objects.
	 * @return void
	 */
	public function log_forms_imported( $forms ): void {

		if ( ! is_array( $forms ) ) {
			return;
		}

		foreach ( $forms as $form ) {

			if ( ! is_array( $form ) || empty( $form['id'] ) ) {
				continue;
			}

			$title = isset( $form['title'] ) ? (string) $form['title'] : '';

			$this->insert_event_log(
				Events::GRAVITYFORMS,
				Actions::FORM_IMPORT,
				array(
					'object_type' => 'gravityforms_form',
					'object_id'   => (int) $form['id'],
					'message'     => sprintf( 'Gravity Forms form "%s" imported.', $title ),
					'context'     => $this->get_common_context(),
				)
			);
		}
	}

	/**
	 * Log each form exported in one batch.
	 *
	 * `gform_form_export_filename` is a filter, not an action - the filename
	 * it hands over must be returned unchanged so the actual export download
	 * still works.
	 *
	 * @param string $filename Export filename.
	 * @param array  $form_ids Form IDs being exported.
	 * @return string
	 */
	public function log_forms_exported( $filename, $form_ids ) {

		foreach ( (array) $form_ids as $form_id ) {

			$form_id = (int) $form_id;
			$title   = class_exists( 'GFAPI' ) ? \GFAPI::get_form( $form_id ) : false;
			$title   = is_array( $title ) && isset( $title['title'] ) ? (string) $title['title'] : '';

			$this->insert_event_log(
				Events::GRAVITYFORMS,
				Actions::FORM_EXPORT,
				array(
					'object_type' => 'gravityforms_form',
					'object_id'   => $form_id,
					'message'     => sprintf( 'Gravity Forms form "%s" exported.', $title ),
					'context'     => $this->get_common_context(),
				)
			);
		}

		return $filename;
	}

	/**
	 * Log a Gravity Forms global setting change.
	 *
	 * `updated_option` fires for every option site-wide, so this bails
	 * immediately for anything not on `KNOWN_SETTINGS` - see that constant's
	 * docblock.
	 *
	 * @param string $option_name Option name.
	 * @param mixed  $old_value   Previous value.
	 * @param mixed  $value       New value.
	 * @return void
	 */
	public function log_settings_changed( $option_name, $old_value, $value ): void {

		if ( ! isset( self::KNOWN_SETTINGS[ $option_name ] ) ) {
			return;
		}

		$label = self::KNOWN_SETTINGS[ $option_name ];

		$this->insert_event_log(
			Events::GRAVITYFORMS,
			Actions::FORM_SETTINGS_CHANGE,
			array(
				'object_type' => 'gravityforms_settings',
				'object_id'   => 0,
				'severity'    => Severity::WARNING,
				'message'     => sprintf( 'Gravity Forms setting "%s" changed.', $label ),
				'context'     => array_merge(
					$this->get_common_context(),
					array(
						'setting_name' => $label,
						'old_value'    => is_scalar( $old_value ) ? $old_value : wp_json_encode( $old_value ),
						'new_value'    => is_scalar( $value ) ? $value : wp_json_encode( $value ),
					)
				),
			)
		);
	}
}
