<?php // phpcs:ignore WordPress.Files.FileName.NotHyphenatedLowercase

namespace Pastmark\ActivityLoggers\Wordfence;

use Pastmark\ActivityLoggers\AbstractLogger;
use Pastmark\Constants\Actions;
use Pastmark\Constants\Events;
use Pastmark\Constants\Severity;

defined( 'ABSPATH' ) || exit;

/**
 * Logs Wordfence's own firewall/IP-blocking events (PM-175, Sprint 12).
 *
 * No `register_events()` here - `LoginSecurityActivityLogger` (constructed
 * first, per `WordfenceIntegration::get_logger_classes()`) already
 * declares the shared `wordfence` event group this logger's actions
 * belong to.
 *
 * Confirmed against the real installed source (Wordfence 9.0.0, this dev
 * environment) that IP-block creation is covered by two genuinely
 * disjoint hooks, not one:
 * - `security_event('block')`/`security_event('throttle')`
 *   (`wfLog::takeBlockingAction()`/`do503()`) - Wordfence's *general*
 *   request-rate-limiting engine (global/crawler/human request and 404
 *   rate limits), automatic and always temporary.
 * - `wordfence_created_ip_pattern_block( $type, $reason, $parameters )`
 *   (`models/block/wfBlock.php`) - fires only for `TYPE_IP_MANUAL`,
 *   `TYPE_IP_AUTOMATIC_PERMANENT`, and `TYPE_PATTERN` blocks
 *   (`wfBlock::createIP()`/`createPattern()`) - manual admin bans,
 *   an automatic block that graduated to permanent, and custom IP-range/
 *   hostname/UA/referrer pattern blocks. `createRateBlock()`/
 *   `createRateThrottle()`/`createLockout()` (the types the hook above
 *   already covers, plus login lockouts) do **not** call this - confirmed
 *   by reading `wfBlock::createIP()`'s own type guard (`if ($type ==
 *   self::TYPE_IP_MANUAL || $type == self::TYPE_IP_AUTOMATIC_PERMANENT)`)
 *   and the fact `createRateBlock()`/`createRateThrottle()`/
 *   `createLockout()` never reach that method at all. Both are mapped to
 *   the same `Actions::FIREWALL_BLOCK` - they're never fired for the same
 *   underlying block, so there's no double-logging risk combining them.
 *
 * `wordfence_deleted_block` (manual block removal) and
 * `wordfence_updated_country_blocking` (country-block list changes,
 * fired from both `wfConfig.php` and `models/block/wfBlock.php` - the
 * same underlying option, safe to hook once) round out real block-
 * lifecycle coverage. `increasedAttackRate` (a `wordfence_security_event`
 * type, aggregate attack-rate anomaly) is included here rather than in
 * `LoginSecurityActivityLogger` since it's a site-wide firewall signal,
 * not login-specific.
 */
class FirewallActivityLogger extends AbstractLogger {

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

		add_action( 'wordfence_security_event', $this->guarded( array( $this, 'log_security_event' ) ), 10, 2 );
		add_action( 'wordfence_created_ip_pattern_block', $this->guarded( array( $this, 'log_block_created' ) ), 10, 3 );
		add_action( 'wordfence_deleted_block', $this->guarded( array( $this, 'log_block_deleted' ) ), 10, 3 );
		add_action( 'wordfence_updated_country_blocking', $this->guarded( array( $this, 'log_country_blocking_changed' ) ), 10, 2 );
	}

	/**
	 * Dispatch a `wordfence_security_event` firing to the right handler -
	 * ignores every type this logger doesn't own (login-specific types
	 * belong to `LoginSecurityActivityLogger` instead).
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
			case 'block':
				$this->log_rate_limit_action( $data, Actions::FIREWALL_BLOCK, 'blocked' );
				break;
			case 'throttle':
				$this->log_rate_limit_action( $data, Actions::FIREWALL_THROTTLE, 'throttled' );
				break;
			case 'increasedAttackRate':
				$this->log_increased_attack_rate( $data );
				break;
		}
	}

	/**
	 * Log a rate-limit-triggered block or throttle.
	 *
	 * @param array  $data   Event data: `ip`, `reason`, `duration`.
	 * @param string $action Action key to log under.
	 * @param string $verb   "blocked" or "throttled", for the message text.
	 * @return void
	 */
	protected function log_rate_limit_action( array $data, string $action, string $verb ): void {

		$ip       = isset( $data['ip'] ) ? (string) $data['ip'] : '';
		$reason   = isset( $data['reason'] ) ? (string) $data['reason'] : '';
		$duration = isset( $data['duration'] ) ? (int) $data['duration'] : 0;

		$this->insert_event_log(
			Events::WORDFENCE,
			$action,
			array(
				'object_type' => 'wordfence-security-event',
				'object_id'   => 0,
				'ip_address'  => $ip,
				'severity'    => 'blocked' === $verb ? Severity::WARNING : Severity::INFO,
				'message'     => sprintf(
					'IP %1$s was %2$s by Wordfence for %3$d seconds. Reason: %4$s',
					$ip,
					$verb,
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

	/**
	 * Log an aggregate attack-rate increase.
	 *
	 * @param array $data Event data: `attackCount`, `attackTable`, `duration`, `ip`.
	 * @return void
	 */
	protected function log_increased_attack_rate( array $data ): void {

		$attack_count = isset( $data['attackCount'] ) ? (int) $data['attackCount'] : 0;
		$duration     = isset( $data['duration'] ) ? (int) $data['duration'] : 0;

		$this->insert_event_log(
			Events::WORDFENCE,
			Actions::ATTACK_RATE_INCREASE,
			array(
				'object_type' => 'wordfence-security-event',
				'object_id'   => 0,
				'severity'    => Severity::WARNING,
				'message'     => sprintf(
					'Wordfence detected an increased attack rate: %1$d attacks in the last %2$d seconds.',
					$attack_count,
					$duration
				),
				'context'     => array_merge(
					$this->get_common_context(),
					array(
						'attack_count' => $attack_count,
						'duration_s'   => $duration,
					)
				),
			)
		);
	}

	/**
	 * Log a manual/permanent/pattern block being created.
	 *
	 * @param int|string   $type       Block type - one of the `wfBlock::TYPE_*` constants.
	 * @param string       $reason     Human-readable block reason.
	 * @param string|array $parameters IP address for IP blocks, or the pattern's parameters for pattern blocks.
	 * @return void
	 */
	public function log_block_created( $type, $reason, $parameters ): void {

		$target = is_array( $parameters ) ? wp_json_encode( $parameters ) : (string) $parameters;

		$this->insert_event_log(
			Events::WORDFENCE,
			Actions::FIREWALL_BLOCK,
			array(
				'object_type' => 'wordfence-security-event',
				'object_id'   => 0,
				'ip_address'  => is_array( $parameters ) ? '' : (string) $parameters,
				'severity'    => Severity::WARNING,
				'message'     => sprintf(
					'Wordfence created a firewall block (%1$s) for "%2$s". Reason: %3$s',
					$this->block_type_label( $type ),
					$target,
					$reason
				),
				'context'     => array_merge(
					$this->get_common_context(),
					array(
						'block_type' => $this->block_type_label( $type ),
						'target'     => $target,
						'reason'     => $reason,
					)
				),
			)
		);
	}

	/**
	 * Log a block rule being manually removed.
	 *
	 * @param int|string        $type       Block type - one of the `wfBlock::TYPE_*` constants.
	 * @param string            $reason     The removed block's reason.
	 * @param string|array|null $parameters IP/pattern parameters (`null` for a country block - there's only one rule to disambiguate).
	 * @return void
	 */
	public function log_block_deleted( $type, $reason, $parameters ): void {

		$target = is_array( $parameters ) ? wp_json_encode( $parameters ) : (string) $parameters;

		$this->insert_event_log(
			Events::WORDFENCE,
			Actions::FIREWALL_UNBLOCK,
			array(
				'object_type' => 'wordfence-security-event',
				'object_id'   => 0,
				'ip_address'  => is_array( $parameters ) || null === $parameters ? '' : (string) $parameters,
				'severity'    => Severity::INFO,
				'message'     => sprintf(
					'Wordfence firewall block (%1$s) removed for "%2$s". Reason was: %3$s',
					$this->block_type_label( $type ),
					'' !== $target ? $target : 'n/a',
					$reason
				),
				'context'     => array_merge(
					$this->get_common_context(),
					array(
						'block_type' => $this->block_type_label( $type ),
						'target'     => $target,
						'reason'     => $reason,
					)
				),
			)
		);
	}

	/**
	 * Log the country-blocking list changing.
	 *
	 * @param mixed $before Previous country-blocking configuration.
	 * @param mixed $after  New country-blocking configuration.
	 * @return void
	 */
	public function log_country_blocking_changed( $before, $after ): void {

		if ( $before === $after ) {
			return;
		}

		$this->insert_event_log(
			Events::WORDFENCE,
			Actions::COUNTRY_BLOCK_CHANGE,
			array(
				'object_type' => 'wordfence-settings',
				'object_id'   => 0,
				'severity'    => Severity::WARNING,
				'message'     => "Wordfence's country-blocking configuration changed.",
				'before_data' => wp_json_encode( $before ),
				'after_data'  => wp_json_encode( $after ),
				'context'     => $this->get_common_context(),
			)
		);
	}

	/**
	 * Resolve a `wfBlock::TYPE_*` constant value to a human-readable
	 * label. Values duplicated here (rather than referencing
	 * `\wfBlock::TYPE_*` directly) since this class must not fatal if
	 * Wordfence's own class isn't loaded yet when it's constructed -
	 * `IntegrationRegistry` only guarantees `is_active()` already
	 * returned true, not that every Wordfence class is autoloadable.
	 *
	 * @param int|string $type Block type constant value.
	 * @return string
	 */
	protected function block_type_label( $type ): string {

		$labels = array(
			1 => 'manual IP block',
			2 => 'Wordfence Security Network block',
			3 => 'country block',
			4 => 'pattern block',
			5 => 'rate-limit block',
			6 => 'rate-limit throttle',
			7 => 'login lockout',
			8 => 'automatic temporary IP block',
			9 => 'automatic permanent IP block',
		);

		return $labels[ (int) $type ] ?? sprintf( 'type %s', (string) $type );
	}
}
