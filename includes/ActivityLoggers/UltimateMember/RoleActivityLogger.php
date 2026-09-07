<?php // phpcs:ignore WordPress.Files.FileName.NotHyphenatedLowercase

namespace Pastmark\ActivityLoggers\UltimateMember;

use Pastmark\ActivityLoggers\AbstractLogger;
use Pastmark\Constants\Actions;
use Pastmark\Constants\Events;
use Pastmark\Constants\Severity;

defined( 'ABSPATH' ) || exit;

/**
 * Logs a member's Ultimate Member role assignment being changed by an
 * admin (PM-174, Sprint 12).
 *
 * No `register_events()` here - `RegistrationActivityLogger` (constructed
 * first, per `UltimateMemberIntegration::get_logger_classes()`) already
 * declares the shared `ultimate-member` event group this logger's action
 * belongs to.
 *
 * Confirmed against the real installed source (Ultimate Member 2.13.0,
 * this dev environment; `includes/core/class-roles-capabilities.php`,
 * `UM_Roles::set_role()`) that this is genuinely an *admin*-only path: it's
 * called from exactly three places, all in `includes/core/class-user.php`,
 * all gated on `current_user_can( 'promote_users' )` and a submitted
 * `$_POST['um-role']` field - the wp-admin "Add existing user"/"Add New
 * User" flows, not a member's own front-end profile-edit form.
 *
 * `set_role()` fires two hooks worth combining into one event -
 * **live-verified against the real installed plugin, which caught a real
 * discrepancy from what UM's own inline hook docs claim**:
 * - `um_member_role_upgrade( $old_role, $new_role )` - fires first, and is
 *   the *only* one of the two that reliably hands over both the old and
 *   the new role: `do_action( 'um_member_role_upgrade', $role,
 *   UM()->user()->profile['role'] )`, where `$role` was captured earlier
 *   from `UM()->roles()->get_um_user_role( $user_id )` before either role
 *   changed. Captured here; doesn't get a `$user_id` argument.
 * - `um_after_user_role_is_updated( $user_id, $role )` - fires
 *   immediately after, same synchronous call. Its own inline doc claims
 *   `$role` is "User role" with no old/new distinction, which reads as
 *   the *new* role - **but a live test showed both this logger's old and
 *   new labels resolving to the same value**, which traced back to the
 *   real call site passing the outer function's `$role` local variable
 *   (the *old* role, captured before the change and never reassigned) as
 *   this hook's second argument, not `UM()->user()->profile['role']` (the
 *   actual new value `um_member_role_upgrade` receives). Confirmed by
 *   reading `set_role()` end to end, not just this one call in isolation.
 *   **This hook's `$role` argument is therefore only used here for a
 *   defensive fallback** (see `log_role_changed()`) - `$user_id` is the
 *   only value this logger actually relies on it for.
 * Both fire back-to-back within one `set_role()` call, never re-entrantly
 * (a single PHP request has no concurrency) - the same "capture in an
 * earlier hook, consume in a later one within the same call" shape
 * `WP2FA\TwoFactorActivityLogger::capture_old_method()` already uses.
 *
 * **No overlap with core `UserActivityLogger::log_role_changed()`**: that
 * method hooks WordPress' own `set_user_role` action, which only fires via
 * `WP_User::set_role()` - UM's `set_role()` above calls `add_role()`/
 * `remove_role()` directly instead, so `set_user_role` never fires on this
 * code path. Confirmed by reading both call paths; no suppression needed.
 */
class RoleActivityLogger extends AbstractLogger {

	/**
	 * {@inheritDoc}
	 *
	 * @var string
	 */
	protected $integration = 'ultimate-member';

	/**
	 * The old/new role pair captured from `um_member_role_upgrade` - the
	 * only hook that reliably hands over both (see class docblock).
	 * Consumed (and unset) the moment `log_role_changed()` runs
	 * immediately after.
	 *
	 * @var array{old: string, new: string}|null
	 */
	protected $pending_role_change = null;

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

		add_action( 'um_member_role_upgrade', $this->guarded( array( $this, 'capture_role_change' ) ), 10, 2 );
		add_action( 'um_after_user_role_is_updated', $this->guarded( array( $this, 'log_role_changed' ) ), 10, 2 );
	}

	/**
	 * Snapshot both the old and new role - see this class' own docblock
	 * for why this hook, not `um_after_user_role_is_updated`, is the
	 * source of truth for the new role too.
	 *
	 * @param string $old_role Role slug before the change.
	 * @param string $new_role Role slug after the change.
	 * @return void
	 */
	public function capture_role_change( $old_role, $new_role ): void {

		$this->pending_role_change = array(
			'old' => (string) $old_role,
			'new' => (string) $new_role,
		);
	}

	/**
	 * Log the role change.
	 *
	 * @param int    $user_id Affected member's user ID.
	 * @param string $role    Role slug `um_after_user_role_is_updated` itself hands over - actually the *old* role in the real installed source (see class docblock); only used as a fallback below if `um_member_role_upgrade` didn't fire for some reason.
	 * @return void
	 */
	public function log_role_changed( $user_id, $role ): void {

		$pending = $this->pending_role_change;

		$this->pending_role_change = null;

		$old_role = $pending['old'] ?? (string) $role;
		$new_role = $pending['new'] ?? (string) $role;

		$user = get_userdata( $user_id );

		if ( ! $user ) {
			return;
		}

		$old_label = $this->resolve_role_label( $old_role );
		$new_label = $this->resolve_role_label( $new_role );

		$acting_user_id = get_current_user_id();
		$acting_user    = $acting_user_id ? get_userdata( $acting_user_id ) : false;

		$this->insert_event_log(
			Events::ULTIMATE_MEMBER,
			Actions::MEMBER_ROLE_CHANGE,
			array(
				'object_type' => 'user',
				'object_id'   => $user_id,
				'user_id'     => $acting_user_id,
				'severity'    => Severity::WARNING,
				'message'     => sprintf(
					'Member "%s"\'s Ultimate Member role was changed from "%s" to "%s" by %s.',
					$user->user_login,
					$old_label,
					$new_label,
					$acting_user ? sprintf( '"%s"', $acting_user->user_login ) : 'an administrator'
				),
				'before_data' => wp_json_encode( array( 'role' => $old_label ) ),
				'after_data'  => wp_json_encode( array( 'role' => $new_label ) ),
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
	 * Resolve a UM role slug to its human-readable name, falling back to
	 * the raw slug when it isn't a recognized role (or is empty, e.g. a
	 * member with no prior UM role).
	 *
	 * @param string $role_slug Role slug.
	 * @return string
	 */
	protected function resolve_role_label( string $role_slug ): string {

		if ( '' === $role_slug ) {
			return __( 'no role', 'pastmark' );
		}

		if ( function_exists( 'UM' ) ) {

			$label = UM()->roles()->get_role_name( $role_slug );

			if ( is_string( $label ) && '' !== $label ) {
				return $label;
			}
		}

		return $role_slug;
	}
}
