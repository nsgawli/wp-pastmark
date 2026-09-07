<?php // phpcs:ignore WordPress.Files.FileName.NotHyphenatedLowercase

namespace Pastmark\ActivityLoggers\Wordfence;

use Pastmark\ActivityLoggers\AbstractLogger;
use Pastmark\Constants\Actions;
use Pastmark\Constants\Events;
use Pastmark\Constants\Severity;

defined( 'ABSPATH' ) || exit;

/**
 * Logs Wordfence's own login-security events (PM-175, Sprint 12) - a
 * blocked breached-password login attempt, and an IP being locked out
 * after repeated failed logins.
 *
 * Wordfence fires one real, documented action for security events,
 * `wordfence_security_event( $event_type, $data, $alertCallback = null )`
 * (`do_action()` calls throughout `lib/wordfenceClass.php`/`lib/wfLog.php`)
 * - confirmed against the real installed source (Wordfence 9.0.0, this dev
 * environment), not assumed from public docs alone. This logger filters to
 * the two event types that are genuinely login-specific:
 * - `breachLogin` - a login attempt using a password known to exist in a
 *   public data breach was blocked (`wordfenceClass.php`, two call sites,
 *   both inside the 2FA/authentication check path).
 * - `loginLockout` - an IP was locked out of login after repeated failed
 *   attempts (`wordfence::lockOutIP()`).
 *
 * **`throttle` is deliberately NOT handled here, correcting this ticket's
 * own original hypothesis** (`sprint-12.md`'s PM-175 entry originally
 * grouped `breachLogin`/`throttle` together as "login throttle" events).
 * Reading the real source shows `throttle` (and its sibling `block`) fire
 * from `wfLog::takeBlockingAction()`, Wordfence's *general* request-rate-
 * limiting engine (`maxGlobalRequests`/`maxRequestsCrawlers`/
 * `max404Crawlers`/`maxRequestsHumans`/`max404Humans`) - not login-specific
 * at all. Handled instead by `FirewallActivityLogger`, where they actually
 * belong. This is a disclosed correction, not a silent scope change - see
 * `docs/sprints/sprint-12.md`'s PM-175 entry for the full finding.
 *
 * `ip_address` is explicitly set from the event's own `ip` value (rather
 * than left to `AbstractLogger`'s own current-request IP default) since
 * these events describe *Wordfence's* view of the offending IP, not
 * necessarily the IP of whoever is logged in (there usually is no
 * authenticated actor for either event type) - still passes through
 * `IpAnonymizer::maybe_anonymize()` the same as any other logger's IP,
 * since that runs centrally in `AbstractLogger::insert_log()`.
 */
class LoginSecurityActivityLogger extends AbstractLogger {

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
		$this->register_events();
	}

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	protected function register_hooks(): void {

		add_action( 'wordfence_security_event', $this->guarded( array( $this, 'log_security_event' ) ), 10, 2 );
	}

	/**
	 * Declare the shared `wordfence` event group - all three of this
	 * integration's loggers' actions live here; only this logger (listed
	 * first in `WordfenceIntegration::get_logger_classes()`) registers it,
	 * matching `BbPressIntegration`'s existing convention.
	 *
	 * @return void
	 */
	protected function register_events(): void {

		add_filter( 'pastmark_registered_events', array( $this, 'add_event_group' ) );
	}

	/**
	 * `pastmark_registered_events` callback.
	 *
	 * @param array $events Registered events, keyed by event type.
	 * @return array
	 */
	public function add_event_group( array $events ): array {

		$events[ Events::WORDFENCE ] = array(
			'label'   => __( 'Wordfence', 'pastmark' ),
			'source'  => 'wordfence',
			'actions' => array(
				array(
					'key'            => Actions::LOGIN_BREACH,
					'label'          => __( 'Login Breach', 'pastmark' ),
					'description'    => __( 'A login attempt using a known-breached password was blocked.', 'pastmark' ),
					'severity'       => Severity::WARNING,
					'severity_label' => Severity::resolve_label( Severity::WARNING ),
				),
				array(
					'key'            => Actions::LOGIN_LOCKOUT,
					'label'          => __( 'Login Lockout', 'pastmark' ),
					'description'    => __( 'An IP was locked out of login after repeated failed attempts.', 'pastmark' ),
					'severity'       => Severity::WARNING,
					'severity_label' => Severity::resolve_label( Severity::WARNING ),
				),
				array(
					'key'            => Actions::FIREWALL_BLOCK,
					'label'          => __( 'Firewall Block', 'pastmark' ),
					'description'    => __( "An IP was blocked by Wordfence's firewall.", 'pastmark' ),
					'severity'       => Severity::WARNING,
					'severity_label' => Severity::resolve_label( Severity::WARNING ),
				),
				array(
					'key'            => Actions::FIREWALL_THROTTLE,
					'label'          => __( 'Firewall Throttle', 'pastmark' ),
					'description'    => __( "An IP was throttled by Wordfence's rate limiting.", 'pastmark' ),
					'severity'       => Severity::INFO,
					'severity_label' => Severity::resolve_label( Severity::INFO ),
				),
				array(
					'key'            => Actions::FIREWALL_UNBLOCK,
					'label'          => __( 'Firewall Unblock', 'pastmark' ),
					'description'    => __( 'A Wordfence block rule was manually removed.', 'pastmark' ),
					'severity'       => Severity::INFO,
					'severity_label' => Severity::resolve_label( Severity::INFO ),
				),
				array(
					'key'            => Actions::COUNTRY_BLOCK_CHANGE,
					'label'          => __( 'Country Block Change', 'pastmark' ),
					'description'    => __( "Wordfence's country-blocking list changed.", 'pastmark' ),
					'severity'       => Severity::WARNING,
					'severity_label' => Severity::resolve_label( Severity::WARNING ),
				),
				array(
					'key'            => Actions::ATTACK_RATE_INCREASE,
					'label'          => __( 'Attack Rate Increase', 'pastmark' ),
					'description'    => __( 'Wordfence detected an aggregate increase in attack rate against the site.', 'pastmark' ),
					'severity'       => Severity::WARNING,
					'severity_label' => Severity::resolve_label( Severity::WARNING ),
				),
				array(
					'key'            => Actions::SECURITY_SETTINGS_CHANGE,
					'label'          => __( 'Security Settings Change', 'pastmark' ),
					'description'    => __( 'A Wordfence firewall or scan configuration setting changed.', 'pastmark' ),
					'severity'       => Severity::WARNING,
					'severity_label' => Severity::resolve_label( Severity::WARNING ),
				),
			),
		);

		return $events;
	}

	/**
	 * Dispatch a `wordfence_security_event` firing to the right handler
	 * based on its event type - ignores every type this logger doesn't
	 * own (see class docblock for why `throttle`/`block` belong to
	 * `FirewallActivityLogger` instead).
	 *
	 * @param string $event_type Wordfence's own event-type slug.
	 * @param array  $data       Event data - shape depends on `$event_type`.
	 * @return void
	 */
	public function log_security_event( $event_type, $data ): void {

		if ( ! is_array( $data ) ) {
			return;
		}

		switch ( $event_type ) {
			case 'breachLogin':
				$this->log_breach_login( $data );
				break;
			case 'loginLockout':
				$this->log_login_lockout( $data );
				break;
		}
	}

	/**
	 * Log a blocked breached-password login attempt.
	 *
	 * @param array $data Event data: `username`, `resetPasswordURL`, `supportURL`, `ip`.
	 * @return void
	 */
	protected function log_breach_login( array $data ): void {

		$username = isset( $data['username'] ) ? (string) $data['username'] : '';
		$ip       = isset( $data['ip'] ) ? (string) $data['ip'] : '';

		$this->insert_event_log(
			Events::WORDFENCE,
			Actions::LOGIN_BREACH,
			array(
				'object_type' => 'wordfence-security-event',
				'object_id'   => 0,
				'ip_address'  => $ip,
				'severity'    => Severity::WARNING,
				'message'     => sprintf(
					'Login attempt for username "%s" blocked - the password used exists on a public data-breach list.',
					$username
				),
				'context'     => array_merge(
					$this->get_common_context(),
					array(
						'target_username' => $username,
						'target_ip'       => $ip,
					)
				),
			)
		);
	}

	/**
	 * Log an IP being locked out of login.
	 *
	 * @param array $data Event data: `ip`, `reason`, `duration`.
	 * @return void
	 */
	protected function log_login_lockout( array $data ): void {

		$ip       = isset( $data['ip'] ) ? (string) $data['ip'] : '';
		$reason   = isset( $data['reason'] ) ? (string) $data['reason'] : '';
		$duration = isset( $data['duration'] ) ? (int) $data['duration'] : 0;

		$this->insert_event_log(
			Events::WORDFENCE,
			Actions::LOGIN_LOCKOUT,
			array(
				'object_type' => 'wordfence-security-event',
				'object_id'   => 0,
				'ip_address'  => $ip,
				'severity'    => Severity::WARNING,
				'message'     => sprintf(
					'IP %1$s was locked out of login for %2$d seconds. Reason: %3$s',
					$ip,
					$duration,
					$reason
				),
				'context'     => array_merge(
					$this->get_common_context(),
					array(
						'target_ip'  => $ip,
						'reason'     => $reason,
						'duration_s' => $duration,
					)
				),
			)
		);
	}
}
