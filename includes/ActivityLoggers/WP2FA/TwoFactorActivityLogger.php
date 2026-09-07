<?php // phpcs:ignore WordPress.Files.FileName.NotHyphenatedLowercase

namespace Pastmark\ActivityLoggers\WP2FA;

use Pastmark\ActivityLoggers\AbstractLogger;
use Pastmark\Constants\Actions;
use Pastmark\Constants\Events;
use Pastmark\Constants\Severity;

defined( 'ABSPATH' ) || exit;

/**
 * The WP 2FA activity logger (PM-172, Sprint 11).
 *
 * Expanded to full WP Activity Log parity the same sprint, at explicit
 * user request, after comparing this logger's original 3-action shape
 * against WP Activity Log's own real WP 2FA sensor (`classes/WPSensors/
 * class-wp-2fa-sensor.php`/`Alerts/class-wp-2fa-custom-alerts.php`,
 * alerts 7800-7812) - the same "compare against the real competitor
 * event set, then expand" step PM-166 (Yoast SEO) already went through in
 * Sprint 10. **11 of WP Activity Log's 13 WP 2FA alerts are covered here;
 * 7805 ("Trusted device was enabled/disabled") and 7806 ("trusted device
 * remember length modified") are deliberately not implemented** -
 * confirmed by reading the real installed source (WP 2FA 4.1.0, this dev
 * environment) that the Trusted Devices feature does not exist anywhere
 * in the free tier at all (`\WP2FA\Extensions\TrustedDevices\Core` is
 * guarded behind `class_exists()` everywhere it's referenced, and no
 * `enable_trusted_devices`/`trusted-devices-period` option keys exist in
 * a real captured `wp_2fa_policy` value) - a disclosed, confirmed-by-
 * source scope decision, not an oversight, per this ticket's own AC
 * requirement to confirm free-vs-Premium gating before implementing.
 *
 * WP 2FA fires very few of its own action hooks relevant to this ticket's
 * scope. It does have real native hooks around a user's own method being
 * set/removed (`wp_2fa_method_has_been_set`, `wp_2fa_before_method_is_
 * removed`/`wp_2fa_after_method_is_removed`, fired from `User_Helper::
 * set_enabled_method_for_user()`/`remove_enabled_method_for_user()`) -
 * but **the admin-side "Remove 2FA" bulk action on the Users list screen
 * (`User_Listing::handle_bulk_actions()`) does not go through either of
 * those**: it calls `User_Helper::remove_all_2fa_meta_for_user()`, which
 * deletes every `wp_2fa_*`-prefixed user-meta key directly via
 * `delete_user_meta()`, bypassing `remove_enabled_method_for_user()` (and
 * its hooks) entirely. Confirmed by reading both functions directly, not
 * assumed symmetric - the same class of gap PM-171 found for bbPress' own
 * front-end-only actions.
 *
 * **Fixed at the design level, not worked around**: this logger bridges
 * WordPress' own generic `updated_user_meta`/`added_user_meta`/`delete_
 * user_meta` actions instead, scoped to WP 2FA's `wp_2fa_enabled_methods`
 * and `wp_2fa_is_locked` meta keys - these fire from *any* code path that
 * touches that meta (confirmed live: both the self-service wizard flow
 * and the admin bulk "Remove 2FA" action ultimately call WordPress' own
 * `update_user_meta()`/`delete_user_meta()`). The `update_user_metadata`/
 * `add_user_metadata` *filters* (not the `updated_*`/`added_*` actions)
 * are used purely to snapshot the pre-write value of
 * `wp_2fa_enabled_methods` - WordPress' own `updated_user_meta`/`added_
 * user_meta` actions only hand over the *new* value, and the old one is
 * unrecoverable by the time they fire - the same technique WP Activity
 * Log's own WP 2FA sensor uses for the identical problem.
 *
 * The global policy (`wp_2fa_policy`, a single option covering
 * enforcement/grace-period/allowed-methods/UI text/...) is bridged via
 * `updated_option`, the same mechanism `WPSettingsActivityLogger` already
 * uses for core settings, decomposed into the same per-concern checks WP
 * Activity Log's own `settings_trigger()` makes (enforcement policy,
 * enforced/excluded lists, per-method toggles, password-reset toggle) -
 * matching Yoast's own per-field granularity precedent in this codebase
 * rather than one aggregated diff, since the ticket explicitly asked for
 * WP Activity Log parity here.
 *
 * Severity mapping: WP 2FA's own alert severities (Informational/Medium/
 * High/Critical) collapse onto Pastmark's five-level scale as Info/
 * Warning/Warning/Critical - Medium and High both map to `warning` since
 * Pastmark has no distinct middle tier between the two, and `error` is
 * reserved elsewhere in this codebase for genuine failures (e.g. a failed
 * plugin install), not "an important policy change happened."
 */
class TwoFactorActivityLogger extends AbstractLogger {

	/**
	 * {@inheritDoc}
	 *
	 * @var string
	 */
	protected $integration = 'wp-2fa';

	/**
	 * WP 2FA's own option/meta-key names this logger bridges.
	 */
	private const POLICY_OPTION   = 'wp_2fa_policy';
	private const METHOD_META_KEY = 'wp_2fa_enabled_methods';
	private const LOCKED_META_KEY = 'wp_2fa_is_locked';

	/**
	 * Site-wide allowed-method policy flags worth their own "method
	 * toggled" event (WP Activity Log's alert 7804), mapped to a
	 * human-readable label. Confirmed against a real captured
	 * `wp_2fa_policy` value in this dev environment - the free tier's
	 * real method flags, not WP Activity Log's own broader (partly
	 * Premium/legacy) `yubico`/`clickatell`/`twilio`/`authy` list.
	 *
	 * @var array<string, string>
	 */
	private const POLICY_METHOD_FLAGS = array(
		'enable_totp'          => 'One-time code via app (TOTP)',
		'enable_email'         => 'One-time code via email',
		'enable_passkeys'      => 'Passkeys',
		'backup_codes_enabled' => 'Backup codes',
	);

	/**
	 * The pre-write value of `wp_2fa_enabled_methods`, captured by
	 * `capture_old_method()`, keyed by user ID - consumed (and unset) the
	 * moment `log_method_set()` runs for that same user.
	 *
	 * @var array<int, string>
	 */
	protected static $pending_old_method = array();

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

		add_action( 'updated_option', $this->guarded( array( $this, 'log_policy_updated' ) ), 10, 3 );

		// Snapshot the pre-write value - must return $check unmodified.
		add_filter( 'update_user_metadata', $this->guarded( array( $this, 'capture_old_method' ) ), 1, 4 );
		add_filter( 'add_user_metadata', $this->guarded( array( $this, 'capture_old_method' ) ), 1, 4 );

		add_action( 'updated_user_meta', $this->guarded( array( $this, 'log_method_set' ) ), 10, 4 );
		add_action( 'added_user_meta', $this->guarded( array( $this, 'log_method_set' ) ), 10, 4 );

		add_action( 'delete_user_meta', $this->guarded( array( $this, 'log_method_removed' ) ), 10, 4 );

		add_action( 'added_user_meta', $this->guarded( array( $this, 'log_user_locked' ) ), 10, 4 );
		add_action( 'updated_user_meta', $this->guarded( array( $this, 'log_user_locked' ) ), 10, 4 );
		add_action( 'delete_user_meta', $this->guarded( array( $this, 'log_user_unlocked' ) ), 10, 4 );
	}

	/**
	 * Declare the `wp-2fa` event group.
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

		$events[ Events::WP_2FA ] = array(
			'label'   => __( 'WP 2FA', 'pastmark' ),
			'source'  => 'wp-2fa',
			'actions' => array(
				array(
					'key'            => Actions::TWO_FACTOR_POLICY_ENFORCE,
					'label'          => __( 'Policy Enforce', 'pastmark' ),
					'description'    => __( '2FA enforcement was turned on, or its enforcement mode changed.', 'pastmark' ),
					'severity'       => Severity::WARNING,
					'severity_label' => Severity::resolve_label( Severity::WARNING ),
				),
				array(
					'key'            => Actions::TWO_FACTOR_POLICY_DISABLE,
					'label'          => __( 'Policy Disable', 'pastmark' ),
					'description'    => __( '2FA enforcement was turned off entirely.', 'pastmark' ),
					'severity'       => Severity::CRITICAL,
					'severity_label' => Severity::resolve_label( Severity::CRITICAL ),
				),
				array(
					'key'            => Actions::TWO_FACTOR_ENFORCED_LIST_CHANGE,
					'label'          => __( 'Enforced List Change', 'pastmark' ),
					'description'    => __( 'The list of roles or users on which 2FA is enforced changed.', 'pastmark' ),
					'severity'       => Severity::WARNING,
					'severity_label' => Severity::resolve_label( Severity::WARNING ),
				),
				array(
					'key'            => Actions::TWO_FACTOR_EXCLUDED_LIST_CHANGE,
					'label'          => __( 'Excluded List Change', 'pastmark' ),
					'description'    => __( 'The list of roles or users excluded from 2FA changed.', 'pastmark' ),
					'severity'       => Severity::WARNING,
					'severity_label' => Severity::resolve_label( Severity::WARNING ),
				),
				array(
					'key'            => Actions::TWO_FACTOR_POLICY_METHOD_TOGGLE,
					'label'          => __( 'Policy Method Toggle', 'pastmark' ),
					'description'    => __( 'A site-wide allowed 2FA method was enabled or disabled.', 'pastmark' ),
					'severity'       => Severity::INFO,
					'severity_label' => Severity::resolve_label( Severity::INFO ),
				),
				array(
					'key'            => Actions::TWO_FACTOR_PASSWORD_RESET_TOGGLE,
					'label'          => __( 'Password Reset Toggle', 'pastmark' ),
					'description'    => __( 'The "require 2FA for password resets" setting was toggled.', 'pastmark' ),
					'severity'       => Severity::WARNING,
					'severity_label' => Severity::resolve_label( Severity::WARNING ),
				),
				array(
					'key'            => Actions::TWO_FACTOR_METHOD_CONFIGURE,
					'label'          => __( 'Method Configure', 'pastmark' ),
					'description'    => __( 'A user configured a 2FA method for the first time.', 'pastmark' ),
					'severity'       => Severity::WARNING,
					'severity_label' => Severity::resolve_label( Severity::WARNING ),
				),
				array(
					'key'            => Actions::TWO_FACTOR_METHOD_CHANGE,
					'label'          => __( 'Method Change', 'pastmark' ),
					'description'    => __( 'A user changed their already-configured 2FA method.', 'pastmark' ),
					'severity'       => Severity::WARNING,
					'severity_label' => Severity::resolve_label( Severity::WARNING ),
				),
				array(
					'key'            => Actions::TWO_FACTOR_METHOD_DISABLE,
					'label'          => __( 'Method Disable', 'pastmark' ),
					'description'    => __( "A user's 2FA method was removed, self-service or admin-initiated.", 'pastmark' ),
					'severity'       => Severity::WARNING,
					'severity_label' => Severity::resolve_label( Severity::WARNING ),
				),
				array(
					'key'            => Actions::TWO_FACTOR_USER_LOCKED,
					'label'          => __( 'User Locked', 'pastmark' ),
					'description'    => __( 'A user was locked out for not configuring 2FA within the grace period.', 'pastmark' ),
					'severity'       => Severity::WARNING,
					'severity_label' => Severity::resolve_label( Severity::WARNING ),
				),
				array(
					'key'            => Actions::TWO_FACTOR_USER_UNLOCKED,
					'label'          => __( 'User Unlocked', 'pastmark' ),
					'description'    => __( 'A previously WP 2FA-locked user was unlocked.', 'pastmark' ),
					'severity'       => Severity::WARNING,
					'severity_label' => Severity::resolve_label( Severity::WARNING ),
				),
			),
		);

		return $events;
	}

	/**
	 * Dispatch a `wp_2fa_policy` option write to each per-concern check
	 * below - mirrors WP Activity Log's own `settings_trigger()` shape,
	 * one event per changed concern rather than one aggregated diff.
	 *
	 * @param string $option    Option name.
	 * @param mixed  $old_value Previous value.
	 * @param mixed  $new_value New value.
	 * @return void
	 */
	public function log_policy_updated( string $option, $old_value, $new_value ): void {

		if ( self::POLICY_OPTION !== $option || ! is_array( $old_value ) || ! is_array( $new_value ) || $old_value === $new_value ) {
			return;
		}

		$this->check_enforcement_policy( $old_value, $new_value );
		$this->check_enforced_list( $old_value, $new_value );
		$this->check_excluded_list( $old_value, $new_value );
		$this->check_policy_method_toggles( $old_value, $new_value );
		$this->check_password_reset_toggle( $old_value, $new_value );
	}

	/**
	 * WP Activity Log alerts 7800/7801: `enforcement-policy` turned on/
	 * changed vs. turned off entirely.
	 *
	 * @param array $before Policy option before the write.
	 * @param array $after  Policy option after the write.
	 * @return void
	 */
	protected function check_enforcement_policy( array $before, array $after ): void {

		$old_policy = $before['enforcement-policy'] ?? '';
		$new_policy = $after['enforcement-policy'] ?? '';

		if ( $old_policy === $new_policy ) {
			return;
		}

		if ( 'do-not-enforce' === $new_policy ) {
			$this->insert_event_log(
				Events::WP_2FA,
				Actions::TWO_FACTOR_POLICY_DISABLE,
				array(
					'object_type' => 'wp-2fa-settings',
					'object_id'   => 0,
					'severity'    => Severity::CRITICAL,
					'message'     => 'WP 2FA enforcement policies have been disabled - 2FA is no longer enforced on any user or role.',
					'before_data' => wp_json_encode( array( 'enforcement-policy' => $old_policy ) ),
					'after_data'  => wp_json_encode( array( 'enforcement-policy' => $new_policy ) ),
					'context'     => $this->get_common_context(),
				)
			);

			return;
		}

		$this->insert_event_log(
			Events::WP_2FA,
			Actions::TWO_FACTOR_POLICY_ENFORCE,
			array(
				'object_type' => 'wp-2fa-settings',
				'object_id'   => 0,
				'severity'    => Severity::WARNING,
				'message'     => sprintf( 'WP 2FA enforcement policy set to "%s".', $new_policy ),
				'before_data' => wp_json_encode( array( 'enforcement-policy' => $old_policy ) ),
				'after_data'  => wp_json_encode( array( 'enforcement-policy' => $new_policy ) ),
				'context'     => $this->get_common_context(),
			)
		);
	}

	/**
	 * WP Activity Log alert 7802: `enforced_roles`/`enforced_users`
	 * changed - checked (and logged) independently, matching WP Activity
	 * Log's own two-separate-triggers shape.
	 *
	 * @param array $before Policy option before the write.
	 * @param array $after  Policy option after the write.
	 * @return void
	 */
	protected function check_enforced_list( array $before, array $after ): void {

		$this->log_list_change_if_changed( $before, $after, 'enforced_roles', 'Enforced roles', Actions::TWO_FACTOR_ENFORCED_LIST_CHANGE, 'enforced' );
		$this->log_list_change_if_changed( $before, $after, 'enforced_users', 'Enforced users', Actions::TWO_FACTOR_ENFORCED_LIST_CHANGE, 'enforced' );
	}

	/**
	 * WP Activity Log alert 7803: `excluded_roles`/`excluded_users`
	 * changed - same shape as `check_enforced_list()`.
	 *
	 * @param array $before Policy option before the write.
	 * @param array $after  Policy option after the write.
	 * @return void
	 */
	protected function check_excluded_list( array $before, array $after ): void {

		$this->log_list_change_if_changed( $before, $after, 'excluded_roles', 'Excluded roles', Actions::TWO_FACTOR_EXCLUDED_LIST_CHANGE, 'excluded' );
		$this->log_list_change_if_changed( $before, $after, 'excluded_users', 'Excluded users', Actions::TWO_FACTOR_EXCLUDED_LIST_CHANGE, 'excluded' );
	}

	/**
	 * Shared list-diffing logic for `check_enforced_list()`/
	 * `check_excluded_list()`.
	 *
	 * @param array  $before Policy option before the write.
	 * @param array  $after  Policy option after the write.
	 * @param string $field  Policy field name (e.g. `enforced_roles`).
	 * @param string $label  Human-readable label for `$field`.
	 * @param string $action Action key to log under.
	 * @param string $verb   "enforced" or "excluded", for the message text.
	 * @return void
	 */
	protected function log_list_change_if_changed( array $before, array $after, string $field, string $label, string $action, string $verb ): void {

		$old_list = (array) ( $before[ $field ] ?? array() );
		$new_list = (array) ( $after[ $field ] ?? array() );

		if ( $old_list === $new_list ) {
			return;
		}

		$this->insert_event_log(
			Events::WP_2FA,
			$action,
			array(
				'object_type' => 'wp-2fa-settings',
				'object_id'   => 0,
				'severity'    => Severity::WARNING,
				'message'     => sprintf( 'Changed the list of %s on which 2FA is %s.', $label, $verb ),
				'before_data' => wp_json_encode( array( $label => $old_list ) ),
				'after_data'  => wp_json_encode( array( $label => $new_list ) ),
				'context'     => $this->get_common_context(),
			)
		);
	}

	/**
	 * WP Activity Log alert 7804: a site-wide allowed-method flag
	 * toggled - one row per changed flag, matching WP Activity Log's own
	 * per-provider loop.
	 *
	 * @param array $before Policy option before the write.
	 * @param array $after  Policy option after the write.
	 * @return void
	 */
	protected function check_policy_method_toggles( array $before, array $after ): void {

		foreach ( self::POLICY_METHOD_FLAGS as $field => $label ) {

			$old_val = $before[ $field ] ?? '';
			$new_val = $after[ $field ] ?? '';

			if ( $old_val === $new_val ) {
				continue;
			}

			$enabled = ! empty( $new_val );

			$this->insert_event_log(
				Events::WP_2FA,
				Actions::TWO_FACTOR_POLICY_METHOD_TOGGLE,
				array(
					'object_type' => 'wp-2fa-settings',
					'object_id'   => 0,
					'message'     => sprintf( 'The 2FA method "%s" was %s.', $label, $enabled ? 'enabled' : 'disabled' ),
					'before_data' => wp_json_encode( array( $label => $old_val ) ),
					'after_data'  => wp_json_encode( array( $label => $new_val ) ),
					'context'     => $this->get_common_context(),
				)
			);
		}
	}

	/**
	 * WP Activity Log alert 7807: "require 2FA for password resets"
	 * toggled.
	 *
	 * @param array $before Policy option before the write.
	 * @param array $after  Policy option after the write.
	 * @return void
	 */
	protected function check_password_reset_toggle( array $before, array $after ): void {

		$old_val = $before['password-reset-2fa-show'] ?? '';
		$new_val = $after['password-reset-2fa-show'] ?? '';

		if ( $old_val === $new_val ) {
			return;
		}

		$this->insert_event_log(
			Events::WP_2FA,
			Actions::TWO_FACTOR_PASSWORD_RESET_TOGGLE,
			array(
				'object_type' => 'wp-2fa-settings',
				'object_id'   => 0,
				'severity'    => Severity::WARNING,
				'message'     => sprintf( 'The "require 2FA for password resets" setting was %s.', ! empty( $new_val ) ? 'enabled' : 'disabled' ),
				'before_data' => wp_json_encode( array( 'password-reset-2fa-show' => $old_val ) ),
				'after_data'  => wp_json_encode( array( 'password-reset-2fa-show' => $new_val ) ),
				'context'     => $this->get_common_context(),
			)
		);
	}

	/**
	 * Snapshot `wp_2fa_enabled_methods`' pre-write value - see this
	 * class' own docblock for why. Must return `$check` unmodified; this
	 * is a filter WordPress uses to let a callback short-circuit the
	 * actual meta write, which this logger must never do.
	 *
	 * @param mixed  $check    Whether to short-circuit the write (unused, returned unmodified).
	 * @param int    $user_id  User ID.
	 * @param string $meta_key Metadata key.
	 * @return mixed
	 */
	public function capture_old_method( $check, $user_id, $meta_key ) {

		if ( self::METHOD_META_KEY === $meta_key ) {
			self::$pending_old_method[ (int) $user_id ] = (string) get_user_meta( (int) $user_id, $meta_key, true );
		}

		return $check;
	}

	/**
	 * Log a user enabling (WP Activity Log alert 7808) or changing
	 * (alert 7809) their 2FA method.
	 *
	 * @param int    $meta_id     Meta ID (unused).
	 * @param int    $user_id     User ID.
	 * @param string $meta_key    Metadata key.
	 * @param mixed  $_meta_value New metadata value.
	 * @return void
	 */
	public function log_method_set( $meta_id, $user_id, $meta_key, $_meta_value ): void {

		if ( self::METHOD_META_KEY !== $meta_key ) {
			return;
		}

		$user_id = (int) $user_id;

		$old = array_key_exists( $user_id, self::$pending_old_method ) ? self::$pending_old_method[ $user_id ] : '';

		unset( self::$pending_old_method[ $user_id ] );

		$new = (string) $_meta_value;

		if ( $old === $new || '' === $new ) {
			return;
		}

		$user = get_userdata( $user_id );

		if ( ! $user instanceof \WP_User ) {
			return;
		}

		$is_first_time = ( '' === $old );

		$log_data = array(
			'object_type' => 'user',
			'object_id'   => $user_id,
			'severity'    => Severity::WARNING,
			'message'     => $is_first_time
				? sprintf( 'User "%s" configured the 2FA method "%s".', $user->user_login, $new )
				: sprintf( 'User "%s" changed their 2FA method from "%s" to "%s".', $user->user_login, $old, $new ),
			'after_data'  => wp_json_encode( array( 'method' => $new ) ),
			'context'     => array_merge(
				$this->get_common_context(),
				array(
					'affected_user_id'    => $user_id,
					'affected_user_login' => $user->user_login,
				)
			),
		);

		if ( ! $is_first_time ) {
			$log_data['before_data'] = wp_json_encode( array( 'method' => $old ) );
		}

		$this->insert_event_log(
			Events::WP_2FA,
			$is_first_time ? Actions::TWO_FACTOR_METHOD_CONFIGURE : Actions::TWO_FACTOR_METHOD_CHANGE,
			$log_data
		);
	}

	/**
	 * Log a user's 2FA method being removed (WP Activity Log alert
	 * 7810) - self-service or an admin-initiated reset for another user
	 * (correctly attributed via `get_current_user_id()` vs. the affected
	 * `$user_id`, per this ticket's own AC).
	 *
	 * @param array  $meta_ids    Deleted meta IDs (unused).
	 * @param int    $user_id     User ID the meta belonged to.
	 * @param string $meta_key    Metadata key.
	 * @param mixed  $_meta_value Metadata value being deleted, if known by the caller (often empty - see fallback below).
	 * @return void
	 */
	public function log_method_removed( $meta_ids, $user_id, $meta_key, $_meta_value ): void {

		if ( self::METHOD_META_KEY !== $meta_key ) {
			return;
		}

		$user_id = (int) $user_id;

		// `delete_user_meta( $user_id, $key )` (no explicit value) hands
		// this hook an empty `$_meta_value` - fall back to reading the
		// still-present value, since this fires before the row is
		// actually removed.
		$old_method = ( '' !== (string) $_meta_value ) ? (string) $_meta_value : (string) get_user_meta( $user_id, $meta_key, true );

		if ( '' === $old_method ) {
			// Nothing was actually configured - not a meaningful removal.
			return;
		}

		$user = get_userdata( $user_id );

		if ( ! $user instanceof \WP_User ) {
			return;
		}

		$acting_user_id  = get_current_user_id();
		$is_admin_action = ( $acting_user_id && $acting_user_id !== $user_id );

		$this->insert_event_log(
			Events::WP_2FA,
			Actions::TWO_FACTOR_METHOD_DISABLE,
			array(
				'object_type' => 'user',
				'object_id'   => $user_id,
				'severity'    => Severity::WARNING,
				'message'     => $is_admin_action
					? sprintf( '2FA method "%s" removed for user "%s" by an administrator. User is no longer using 2FA.', $old_method, $user->user_login )
					: sprintf( 'User "%s" removed their 2FA method "%s". User is no longer using 2FA.', $user->user_login, $old_method ),
				'before_data' => wp_json_encode( array( 'method' => $old_method ) ),
				'context'     => array_merge(
					$this->get_common_context(),
					array(
						'affected_user_id'    => $user_id,
						'affected_user_login' => $user->user_login,
						'admin_initiated'     => $is_admin_action,
					)
				),
			)
		);
	}

	/**
	 * Log a user being locked out for not configuring 2FA within the
	 * grace period (WP Activity Log alert 7811).
	 *
	 * Fires whenever `wp_2fa_is_locked` is set to a truthy value -
	 * `User_Helper::lock_user_account_if_needed()` (`includes/classes/
	 * Admin/Helpers/class-user-helper.php`) only ever sets it to `true`,
	 * confirmed by reading the real installed source, so no separate
	 * "was it actually set to locked, not unlocked" check is needed
	 * beyond the truthy guard below.
	 *
	 * @param int    $meta_id     Meta ID (unused).
	 * @param int    $user_id     User ID.
	 * @param string $meta_key    Metadata key.
	 * @param mixed  $_meta_value New metadata value.
	 * @return void
	 */
	public function log_user_locked( $meta_id, $user_id, $meta_key, $_meta_value ): void {

		if ( self::LOCKED_META_KEY !== $meta_key || empty( $_meta_value ) ) {
			return;
		}

		$user_id = (int) $user_id;
		$user    = get_userdata( $user_id );

		if ( ! $user instanceof \WP_User ) {
			return;
		}

		$this->insert_event_log(
			Events::WP_2FA,
			Actions::TWO_FACTOR_USER_LOCKED,
			array(
				'object_type' => 'user',
				'object_id'   => $user_id,
				'severity'    => Severity::WARNING,
				'message'     => sprintf( 'User "%s" was locked out for not configuring 2FA within the grace period.', $user->user_login ),
				'context'     => array_merge(
					$this->get_common_context(),
					array(
						'affected_user_id'    => $user_id,
						'affected_user_login' => $user->user_login,
					)
				),
			)
		);
	}

	/**
	 * Log a previously-locked user being unlocked (WP Activity Log alert
	 * 7812).
	 *
	 * @param array  $meta_ids    Deleted meta IDs (unused).
	 * @param int    $user_id     User ID.
	 * @param string $meta_key    Metadata key.
	 * @param mixed  $_meta_value Metadata value being deleted (unused).
	 * @return void
	 */
	public function log_user_unlocked( $meta_ids, $user_id, $meta_key, $_meta_value ): void {

		if ( self::LOCKED_META_KEY !== $meta_key ) {
			return;
		}

		$user_id = (int) $user_id;
		$user    = get_userdata( $user_id );

		if ( ! $user instanceof \WP_User ) {
			return;
		}

		$this->insert_event_log(
			Events::WP_2FA,
			Actions::TWO_FACTOR_USER_UNLOCKED,
			array(
				'object_type' => 'user',
				'object_id'   => $user_id,
				'severity'    => Severity::WARNING,
				'message'     => sprintf( 'User "%s" was unlocked and can proceed to log in and configure 2FA.', $user->user_login ),
				'context'     => array_merge(
					$this->get_common_context(),
					array(
						'affected_user_id'    => $user_id,
						'affected_user_login' => $user->user_login,
					)
				),
			)
		);
	}
}
