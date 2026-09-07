<?php // phpcs:ignore WordPress.Files.FileName.NotHyphenatedLowercase

namespace Pastmark\ActivityLoggers\WPForms;

use Pastmark\ActivityLoggers\AbstractLogger;
use Pastmark\Constants\Actions;
use Pastmark\Constants\Events;
use Pastmark\Constants\Severity;

defined( 'ABSPATH' ) || exit;

/**
 * WPForms global-settings, service-integration, and addon-lifecycle activity
 * logger (PM-170).
 *
 * Added on real user request, comparing this integration's original Sprint 8
 * scope (`FormActivityLogger`/`EntryActivityLogger`, 8 actions) against WP
 * Activity Log's own WPForms sensor (`wp-security-audit-log`'s alerts 5509/
 * 5510/5511 - 5508's "access settings" alert is a WPForms Pro-only feature,
 * not available in the Lite install this was verified against, so it's
 * deliberately out of scope here rather than built untestable).
 *
 * Unlike WP Activity Log's own approach (sniffing raw `updated_option`/
 * `added_option` calls across several option names, with a large hand-built
 * per-capability diff for the Pro-only access-settings case), general
 * WPForms settings changes have a real, dedicated, already-diffed hook -
 * `wpforms_settings_updated`, fired by `wpforms_update_settings()` itself -
 * covering the "Currency settings changed" case (5509) as just one more
 * changed key rather than needing its own separate detection. Only the
 * provider/service-integration option (`wpforms_providers`) genuinely
 * bypasses that hook (confirmed by reading `includes/class-providers.php`)
 * and needs its own narrowly-scoped `updated_option` listener.
 */
class FormSettingsActivityLogger extends AbstractLogger {

	/**
	 * {@inheritDoc}
	 *
	 * @var string
	 */
	protected $integration = 'wpforms';

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

		add_action( 'wpforms_settings_updated', $this->guarded( array( $this, 'log_settings_updated' ) ), 10, 3 );

		add_action( 'updated_option', $this->guarded( array( $this, 'log_providers_option_changed' ) ), 10, 3 );

		add_action( 'wpforms_plugin_activated', $this->guarded( array( $this, 'log_addon_activated' ) ), 10, 1 );

		add_action( 'wpforms_plugin_deactivated', $this->guarded( array( $this, 'log_addon_deactivated' ) ), 10, 1 );
	}

	/**
	 * Log a change to WPForms' own general plugin settings.
	 *
	 * `wpforms_settings_updated` fires on every "Save Settings" click
	 * regardless of whether anything actually changed - `$settings` and
	 * `$old_settings` are compared here so a no-op resave doesn't log.
	 *
	 * @param array $settings     New settings.
	 * @param bool  $updated      Whether `update_option()` reported a change.
	 * @param array $old_settings Previous settings.
	 * @return void
	 */
	public function log_settings_updated( $settings, $updated, $old_settings ): void {

		if ( ! is_array( $settings ) || ! is_array( $old_settings ) ) {
			return;
		}

		$changed_keys = array();

		foreach ( $settings as $key => $value ) {

			if ( ! array_key_exists( $key, $old_settings ) || $old_settings[ $key ] !== $value ) {
				$changed_keys[] = $key;
			}
		}

		foreach ( $old_settings as $key => $value ) {

			if ( ! array_key_exists( $key, $settings ) ) {
				$changed_keys[] = $key;
			}
		}

		if ( empty( $changed_keys ) ) {
			return;
		}

		$changed_keys = array_values( array_unique( $changed_keys ) );

		$this->insert_event_log(
			Events::WPFORMS,
			Actions::FORM_SETTINGS_CHANGE,
			array(
				'object_type' => 'wpforms_settings',
				'object_id'   => 0,
				'severity'    => Severity::WARNING,
				'message'     => sprintf( 'WPForms settings changed (%s).', implode( ', ', $changed_keys ) ),
				'before_data' => wp_json_encode( array_intersect_key( $old_settings, array_flip( $changed_keys ) ), JSON_PRETTY_PRINT ),
				'after_data'  => wp_json_encode( array_intersect_key( $settings, array_flip( $changed_keys ) ), JSON_PRETTY_PRINT ),
				'context'     => array_merge(
					$this->get_common_context(),
					array( 'changed_keys' => $changed_keys )
				),
			)
		);
	}

	/**
	 * Log a change to WPForms' `wpforms_providers` option - the one
	 * settings surface that bypasses `wpforms_update_settings()`/
	 * `wpforms_settings_updated` entirely (each provider's own admin screen
	 * calls `update_option( 'wpforms_providers', ... )` directly).
	 *
	 * @param string $option_name Option name.
	 * @param mixed  $old_value   Previous value.
	 * @param mixed  $value       New value.
	 * @return void
	 */
	public function log_providers_option_changed( $option_name, $old_value, $value ): void {

		if ( 'wpforms_providers' !== $option_name || $old_value === $value ) {
			return;
		}

		$this->insert_event_log(
			Events::WPFORMS,
			Actions::SERVICE_INTEGRATION_CHANGE,
			array(
				'object_type' => 'wpforms_settings',
				'object_id'   => 0,
				'severity'    => Severity::WARNING,
				'message'     => 'WPForms service integration (provider connection) changed.',
				'before_data' => is_array( $old_value ) ? wp_json_encode( $old_value, JSON_PRETTY_PRINT ) : null,
				'after_data'  => is_array( $value ) ? wp_json_encode( $value, JSON_PRETTY_PRINT ) : null,
				'context'     => $this->get_common_context(),
			)
		);
	}

	/**
	 * Log a WPForms addon being activated (or installed - WPForms' own
	 * Addons page fires the same `wpforms_plugin_activated` action for
	 * both, with no reliable way to distinguish the two here).
	 *
	 * @param string $plugin Addon plugin basename.
	 * @return void
	 */
	public function log_addon_activated( $plugin ): void {

		$this->log_addon_event( $plugin, Actions::ADDON_ACTIVATE, 'activated', Severity::INFO );
	}

	/**
	 * Log a WPForms addon being deactivated.
	 *
	 * @param string $plugin Addon plugin basename.
	 * @return void
	 */
	public function log_addon_deactivated( $plugin ): void {

		$this->log_addon_event( $plugin, Actions::ADDON_DEACTIVATE, 'deactivated', Severity::WARNING );
	}

	/**
	 * Shared implementation for the two addon lifecycle events above.
	 *
	 * @param string $plugin   Addon plugin basename/path.
	 * @param string $action   `Actions::` constant to log.
	 * @param string $verb     Verb for the log message.
	 * @param string $severity Severity to log at.
	 * @return void
	 */
	protected function log_addon_event( $plugin, string $action, string $verb, string $severity ): void {

		if ( empty( $plugin ) ) {
			return;
		}

		$name = preg_replace( '/\.[^.]+$/', '', basename( (string) $plugin ) );
		$name = ucwords( str_replace( '-', ' ', (string) $name ) );

		$this->insert_event_log(
			Events::WPFORMS,
			$action,
			array(
				'object_type' => 'wpforms_addon',
				'object_id'   => 0,
				'severity'    => $severity,
				'message'     => sprintf( 'WPForms addon "%1$s" %2$s.', $name, $verb ),
				'context'     => array_merge(
					$this->get_common_context(),
					array( 'plugin' => (string) $plugin )
				),
			)
		);
	}
}
