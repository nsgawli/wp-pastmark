<?php // phpcs:ignore WordPress.Files.FileName.NotHyphenatedLowercase

namespace Pastmark\ActivityLoggers\Wordfence;

use Pastmark\ActivityLoggers\AbstractLogger;
use Pastmark\Constants\Actions;
use Pastmark\Constants\Events;
use Pastmark\Constants\Severity;

defined( 'ABSPATH' ) || exit;

/**
 * Logs Wordfence's own firewall/scan configuration changes (PM-175,
 * Sprint 12).
 *
 * No `register_events()` here - `LoginSecurityActivityLogger` (constructed
 * first) already declares the shared `wordfence` event group this
 * logger's action belongs to.
 *
 * **This ticket's own original hypothesis - "likely an `update_option`-
 * based bridge, matching `WPSettingsActivityLogger`'s existing pattern" -
 * is wrong, confirmed by reading the real installed source (Wordfence
 * 9.0.0, this dev environment) rather than assumed**: Wordfence stores
 * its own settings in a custom `wp_wfConfig` database table
 * (`wfConfig::set()`/`wfConfig::table()`), never touching WordPress'
 * Options API at all - `update_option`/`updated_option` simply never fire
 * for a Wordfence setting change, so no such bridge is possible. Instead,
 * `wfConfig::set()` funnels every write through its own single choke
 * point, `wfConfig::_handleActionHooks()`, which fires **~50 separate,
 * individually-named, genuinely real `do_action()` calls** (one per
 * settings concern it explicitly lists in a `switch` statement) - a far
 * richer native hook surface than a single generic bridge would have
 * given, but also one this ticket's own single "a settings/configuration
 * change logs its own event" AC doesn't ask to fully enumerate.
 *
 * **Deliberately curated to the firewall/scan subset this ticket's own
 * Description names** (not all ~50): WAF mode, WAF protection level,
 * general rate-limiting on/off, scan options, and scan schedule. The
 * other ~45 (individual rate-limit thresholds, allowed-IP/service lists,
 * login-security sub-settings, license key, audit log mode, ...) are real
 * and hookable the same way, but out of this ticket's stated "firewall/
 * scan configuration" scope - a disclosed scope decision, not an
 * oversight, matching this project's standing practice of disclosing
 * scope decisions rather than silently over- or under-implementing. Each
 * curated hook logs under the same shared `Actions::SECURITY_SETTINGS_
 * CHANGE`, matching `WPSettingsActivityLogger`'s own one-action-many-
 * triggers shape (the ticket's own suggested precedent) rather than WP2FA's
 * fully-granular-per-concern actions, since no explicit user request for
 * full WP Activity Log parity exists for this ticket the way it did for
 * WP2FA's.
 */
class SettingsActivityLogger extends AbstractLogger {

	/**
	 * {@inheritDoc}
	 *
	 * @var string
	 */
	protected $integration = 'wordfence';

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
	 * @return void
	 */
	protected function register_hooks(): void {

		add_action( 'wordfence_waf_mode', $this->guarded( array( $this, 'log_waf_mode_changed' ) ), 10, 2 );
		add_action( 'wordfence_waf_changed_protection_level', $this->guarded( array( $this, 'log_protection_level_changed' ) ), 10, 2 );
		add_action( 'wordfence_toggled_general_rate_limiting_blocking', $this->guarded( array( $this, 'log_rate_limiting_toggled' ) ), 10, 2 );
		add_action( 'wordfence_updated_scan_options', $this->guarded( array( $this, 'log_scan_options_changed' ) ), 10, 2 );
		add_action( 'wordfence_updated_scan_schedule', $this->guarded( array( $this, 'log_scan_schedule_changed' ) ), 10, 2 );
	}

	/**
	 * Log the WAF mode changing (e.g. disabled/learning-mode/enabled).
	 *
	 * @param string $before Previous mode.
	 * @param string $after  New mode.
	 * @return void
	 */
	public function log_waf_mode_changed( $before, $after ): void {

		$this->log_setting_change(
			'WAF mode',
			sprintf( 'Wordfence WAF mode changed from "%1$s" to "%2$s".', $before, $after ),
			$before,
			$after,
			Severity::CRITICAL
		);
	}

	/**
	 * Log the WAF protection level changing (basic/extended).
	 *
	 * @param string $before Previous level.
	 * @param string $after  New level.
	 * @return void
	 */
	public function log_protection_level_changed( $before, $after ): void {

		$this->log_setting_change(
			'WAF protection level',
			sprintf( 'Wordfence WAF protection level changed from "%1$s" to "%2$s".', $before, $after ),
			$before,
			$after,
			Severity::WARNING
		);
	}

	/**
	 * Log the general rate-limiting/advanced-blocking setting being
	 * toggled.
	 *
	 * @param bool $before Previous status.
	 * @param bool $after  New status.
	 * @return void
	 */
	public function log_rate_limiting_toggled( $before, $after ): void {

		$this->log_setting_change(
			'General rate limiting',
			sprintf(
				'Wordfence general rate limiting/advanced blocking was %s.',
				$after ? 'enabled' : 'disabled'
			),
			$before,
			$after,
			Severity::WARNING
		);
	}

	/**
	 * Log the scan options changing.
	 *
	 * @param array $before Previous options.
	 * @param array $after  New options.
	 * @return void
	 */
	public function log_scan_options_changed( $before, $after ): void {

		$this->log_setting_change(
			'Scan options',
			'Wordfence scan options changed.',
			$before,
			$after,
			Severity::WARNING
		);
	}

	/**
	 * Log the scan schedule changing.
	 *
	 * @param array $before Previous schedule/options.
	 * @param array $after  New schedule/options.
	 * @return void
	 */
	public function log_scan_schedule_changed( $before, $after ): void {

		$this->log_setting_change(
			'Scan schedule',
			'Wordfence scan schedule changed.',
			$before,
			$after,
			Severity::WARNING
		);
	}

	/**
	 * Shared insert helper for every settings-change hook above.
	 *
	 * @param string $label    Human-readable setting name, stored in `context['setting']`.
	 * @param string $message  Log message.
	 * @param mixed  $before   Previous value.
	 * @param mixed  $after    New value.
	 * @param string $severity Severity level.
	 * @return void
	 */
	protected function log_setting_change( string $label, string $message, $before, $after, string $severity ): void {

		$this->insert_event_log(
			Events::WORDFENCE,
			Actions::SECURITY_SETTINGS_CHANGE,
			array(
				'object_type' => 'wordfence-settings',
				'object_id'   => 0,
				'severity'    => $severity,
				'message'     => $message,
				'before_data' => wp_json_encode( $before ),
				'after_data'  => wp_json_encode( $after ),
				'context'     => array_merge(
					$this->get_common_context(),
					array( 'setting' => $label )
				),
			)
		);
	}
}
