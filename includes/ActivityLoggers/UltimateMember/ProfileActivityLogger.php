<?php // phpcs:ignore WordPress.Files.FileName.NotHyphenatedLowercase

namespace Pastmark\ActivityLoggers\UltimateMember;

use Pastmark\ActivityLoggers\AbstractLogger;
use Pastmark\ActivityLoggers\UserActivityLogger;
use Pastmark\Constants\Actions;
use Pastmark\Constants\Events;
use Pastmark\Constants\Severity;

defined( 'ABSPATH' ) || exit;

/**
 * Logs a member's Ultimate Member profile-field changes (PM-174, Sprint
 * 12) - a front-end self-edit, or an admin editing another member's
 * profile.
 *
 * No `register_events()` here - `RegistrationActivityLogger` (constructed
 * first) already declares the shared `ultimate-member` event group this
 * logger's action belongs to.
 *
 * Ultimate Member does not model a member's profile as a custom post type
 * - it's plain `WP_User` core columns (email/display name/website/role)
 * plus user meta (first/last name, nickname, bio, and any custom
 * registration-form field), all written together in one pass by
 * `UM()->user()->update_profile()` (`includes/core/class-user.php`).
 * Confirmed against the real installed source (Ultimate Member 2.13.0,
 * this dev environment), that single call is bracketed by two real,
 * documented hooks this logger uses to diff the whole batch as one event:
 * - `um_user_pre_updating_profile( $to_update, $user_id, $form_data )`
 *   fires first, handing the *new* values about to be written -
 *   `capture_before_values()` uses it purely to snapshot each field's
 *   pre-write value, since by the time the "after" hook fires the write
 *   has already happened and the old value is gone.
 * - `um_after_user_updated( $user_id, $args, $to_update )` fires right
 *   after the write completes - `log_profile_updated()` builds and logs
 *   the diff from here.
 *
 * Password fields (`user_pass`/`user_password`) are deliberately never
 * added to the diff at all, not even masked - `SensitiveDataMasker`
 * matches sensitive keys by *exact* name (`password`/`pass`/`pwd`, see
 * `docs/SECURITY-PRIVACY-BASELINE.md`), and UM's own key names
 * (`user_pass`/`user_password`) don't exactly match any of those, so
 * relying on the masker here would silently leak a password hash into
 * `before_data`/`after_data`. A password change is instead surfaced as a
 * plain "Password" entry in the changed-fields list, with no value stored
 * either side - the same "note that it changed, never store it" choice
 * core `UserActivityLogger::log_profile_updated()` already makes for
 * `user_pass`.
 *
 * **Overlap with core `UserActivityLogger`, confirmed and fixed (this
 * ticket's own AC):** `update_profile()`'s per-field writes still go
 * through WordPress' own `update_user_meta()`/`wp_update_user()`, which
 * fire WordPress' own generic hooks - `UserActivityLogger` already
 * listens to those (`profile_update`, `updated_user_meta`,
 * `added_user_meta`) and would otherwise log the *same* single profile
 * edit a second time: once here (as one richer `ultimate-member` event)
 * and once (or several times, once per changed meta key) as a generic
 * `user` event. Fixed at the source: `capture_before_values()` sets
 * `UserActivityLogger::$suppress_generic_profile_log_for_user[ $user_id ]`
 * for the duration of the pre -> after window, so `UserActivityLogger`'s
 * own `profile_update`/`updated_user_meta`/`added_user_meta` handlers
 * defer to this logger instead of double-logging. `log_role_changed()`
 * is deliberately left un-suppressed - see `RoleActivityLogger`'s own
 * docblock for why no overlap exists there.
 *
 * **`display_name` is tracked defensively, even when it isn't one of the
 * fields actually being edited** - live-verified against the real
 * installed plugin: whenever `update_profile()` calls `wp_update_user()`
 * at all (any core column changing is enough to trigger that), WordPress
 * core's own `wp_insert_user()` resets `display_name` to the user's login
 * unless `display_name` is explicitly present in the array passed to it -
 * confirmed by reading `wp_insert_user()` (`wp-includes/user.php`) - and
 * UM's `update_profile()` never includes it unless the caller explicitly
 * changed it. Since this logger's own suppression above stops
 * `UserActivityLogger` from catching that incidental reset too, silently
 * relying on `$to_update`'s own keys here would mean *nobody* logs it.
 */
class ProfileActivityLogger extends AbstractLogger {

	/**
	 * {@inheritDoc}
	 *
	 * @var string
	 */
	protected $integration = 'ultimate-member';

	/**
	 * `$to_update` keys UM itself routes through `wp_update_user()` rather
	 * than plain `update_user_meta()` (`UM_User::$update_user_keys`,
	 * `includes/core/class-user.php`) - excluding `role`, handled
	 * separately below since it isn't a plain `WP_User` scalar property,
	 * and the password keys, which are never diffed at all (see class
	 * docblock).
	 */
	private const CORE_KEYS = array( 'user_email', 'display_name', 'user_url' );

	/**
	 * Password keys UM writes via `wp_update_user()` - never diffed, see
	 * class docblock.
	 */
	private const PASSWORD_KEYS = array( 'user_pass', 'user_password' );

	/**
	 * Human-readable labels for the changed-fields list. Any key not
	 * listed here still gets diffed, just under its raw meta-key name.
	 *
	 * @var array<string, string>
	 */
	private const FIELD_LABELS = array(
		'user_email'   => 'Email',
		'display_name' => 'Display Name',
		'user_url'     => 'Website',
		'role'         => 'Role',
		'first_name'   => 'First Name',
		'last_name'    => 'Last Name',
		'nickname'     => 'Nickname',
		'description'  => 'Biographical Info',
	);

	/**
	 * Pre-write snapshot of every non-password field about to change,
	 * keyed by user ID - captured by `capture_before_values()`, consumed
	 * (and unset) by `log_profile_updated()`.
	 *
	 * @var array<int, array<string, mixed>>
	 */
	protected $pending_before = array();

	/**
	 * Whether a password change was part of the same save, keyed by user
	 * ID - captured/consumed alongside `$pending_before`.
	 *
	 * @var array<int, bool>
	 */
	protected $pending_password_changed = array();

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

		add_action( 'um_user_pre_updating_profile', $this->guarded( array( $this, 'capture_before_values' ) ), 10, 3 );
		add_action( 'um_after_user_updated', $this->guarded( array( $this, 'log_profile_updated' ) ), 10, 3 );
	}

	/**
	 * Snapshot each about-to-change field's old value, and mark this
	 * user's writes as UM-driven so `UserActivityLogger` defers to this
	 * class - see this class' own docblock.
	 *
	 * @param array $to_update Fields about to be written (new values).
	 * @param int   $user_id   User ID.
	 * @param array $form_data UM form data (unused here).
	 * @return void
	 */
	public function capture_before_values( $to_update, $user_id, $form_data ): void {

		if ( ! is_array( $to_update ) || empty( $to_update ) ) {
			return;
		}

		$user_id = (int) $user_id;
		$user    = get_userdata( $user_id );

		if ( ! $user ) {
			return;
		}

		UserActivityLogger::$suppress_generic_profile_log_for_user[ $user_id ] = true;

		$before           = array();
		$password_changed = false;

		foreach ( $to_update as $key => $new_value ) {

			if ( in_array( $key, self::PASSWORD_KEYS, true ) ) {
				$password_changed = true;
				continue;
			}

			if ( 'role' === $key ) {
				$before[ $key ] = ! empty( $user->roles ) ? $user->roles[0] : '';
				continue;
			}

			if ( in_array( $key, self::CORE_KEYS, true ) ) {
				$before[ $key ] = $user->$key;
				continue;
			}

			$before[ $key ] = get_user_meta( $user_id, $key, true );
		}

		/*
		 * Defensively track `display_name` too when this batch will
		 * trigger `wp_update_user()` at all (any core column or the
		 * password changing is enough) - see class docblock for the WP
		 * core reset-to-login quirk this guards against.
		 */
		if ( ! array_key_exists( 'display_name', $before )
			&& ( $password_changed
				|| array_key_exists( 'role', $before )
				|| array_intersect_key( array_flip( self::CORE_KEYS ), $before ) )
		) {
			$before['display_name'] = $user->display_name;
		}

		$this->pending_before[ $user_id ]           = $before;
		$this->pending_password_changed[ $user_id ] = $password_changed;
	}

	/**
	 * Build and log the diff.
	 *
	 * @param int   $user_id   User ID.
	 * @param array $args      Form/args data (unused).
	 * @param array $to_update Fields that were written (unused - the pre-write snapshot already recorded which keys to re-check).
	 * @return void
	 */
	public function log_profile_updated( $user_id, $args, $to_update ): void {

		$user_id = (int) $user_id;

		$before           = $this->pending_before[ $user_id ] ?? array();
		$password_changed = $this->pending_password_changed[ $user_id ] ?? false;

		unset( $this->pending_before[ $user_id ], $this->pending_password_changed[ $user_id ] );
		unset( UserActivityLogger::$suppress_generic_profile_log_for_user[ $user_id ] );

		$user = get_userdata( $user_id );

		if ( ! $user ) {
			return;
		}

		$changed_labels = array();
		$before_data    = array();
		$after_data     = array();

		foreach ( $before as $key => $old_value ) {

			if ( 'role' === $key ) {
				$new_value = ! empty( $user->roles ) ? $user->roles[0] : '';
			} elseif ( in_array( $key, self::CORE_KEYS, true ) ) {
				$new_value = $user->$key;
			} else {
				$new_value = get_user_meta( $user_id, $key, true );
			}

			if ( $old_value === $new_value ) {
				continue;
			}

			$changed_labels[]    = self::FIELD_LABELS[ $key ] ?? $key;
			$before_data[ $key ] = $old_value;
			$after_data[ $key ]  = $new_value;
		}

		if ( empty( $changed_labels ) && ! $password_changed ) {
			// Nothing this logger tracks actually changed - e.g. a save
			// that only touched a banned/uploader-only key.
			return;
		}

		if ( $password_changed ) {
			$changed_labels[] = __( 'Password', 'pastmark' );
		}

		$acting_user_id  = get_current_user_id();
		$is_admin_action = ( $acting_user_id && $acting_user_id !== $user_id );

		$this->insert_event_log(
			Events::ULTIMATE_MEMBER,
			Actions::MEMBER_PROFILE_UPDATE,
			array(
				'object_type' => 'user',
				'object_id'   => $user_id,
				'user_id'     => $acting_user_id,
				'severity'    => Severity::INFO,
				'message'     => $is_admin_action
					? sprintf(
						'An administrator updated the Ultimate Member profile for "%s". Changed: %s.',
						$user->user_login,
						implode( ', ', $changed_labels )
					)
					: sprintf(
						'Member "%s" updated their Ultimate Member profile. Changed: %s.',
						$user->user_login,
						implode( ', ', $changed_labels )
					),
				'before_data' => ! empty( $before_data ) ? wp_json_encode( $before_data ) : '',
				'after_data'  => ! empty( $after_data ) ? wp_json_encode( $after_data ) : '',
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
}
