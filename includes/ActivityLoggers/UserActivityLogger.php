<?php // phpcs:ignore WordPress.Files.FileName.NotHyphenatedLowercase
namespace Pastmark\ActivityLoggers;

use Pastmark\Constants\Severity;
use Pastmark\Constants\Events;
use Pastmark\Constants\Actions;
use WP_User;

defined( 'ABSPATH' ) || exit;

/**
 * User activity logger class.
 */
class UserActivityLogger extends AbstractLogger {

	/**
	 * Standard profile fields stored as core `wp_users` columns, mapped to
	 * a human-readable label for the profile-update diff.
	 *
	 * Safe to read straight off the `$old_user_data` snapshot handed to
	 * `profile_update` - unlike the usermeta-backed fields below, these
	 * live on the row itself so the snapshot already holds the old value.
	 *
	 * @var array<string, string>
	 */
	protected const TRACKED_PROFILE_CORE_FIELDS = array(
		'display_name' => 'Display Name',
		'user_url'     => 'Website',
	);

	/**
	 * Standard profile fields stored as usermeta, mapped to a
	 * human-readable label for the profile-update diff.
	 *
	 * NOT safe to read off `$old_user_data`: WP_User resolves these
	 * lazily via `get_user_meta()` on first access, and by the time
	 * `profile_update` fires the meta has already been overwritten - so
	 * the "old" snapshot would silently return the new value instead. The
	 * old value is captured earlier instead, via
	 * `capture_user_meta_before_update()`.
	 *
	 * @var array<string, string>
	 */
	protected const TRACKED_PROFILE_META_FIELDS = array(
		'first_name'  => 'First Name',
		'last_name'   => 'Last Name',
		'nickname'    => 'Nickname',
		'description' => 'Biographical Info',
	);

	/**
	 * User meta values captured before an update, keyed by "{user_id}:{meta_key}".
	 *
	 * `updated_user_meta` fires after the row is already saved, so the
	 * previous value has to be captured earlier via the
	 * `update_user_metadata` filter to allow a before/after diff.
	 *
	 * @var array<string, mixed>
	 */
	protected $pending_user_meta_values = array();

	/**
	 * Old values of `TRACKED_PROFILE_META_FIELDS`, captured before an
	 * update, keyed by "{user_id}:{meta_key}". Same capture problem as
	 * `$pending_user_meta_values` above, kept separate so tracking a
	 * profile field for the diff doesn't also spam the generic "custom
	 * field updated" log (see `is_reserved_user_meta_key()`).
	 *
	 * @var array<string, mixed>
	 */
	protected $pending_profile_field_values = array();

	/**
	 * User IDs created earlier in this same request, keyed by ID.
	 *
	 * Creating a user through wp-admin (with the "Send User Notification"
	 * box checked) sends a "set your password" email in the very same
	 * request, which under the hood reuses the password-reset-key
	 * mechanism (`get_password_reset_key()`) - firing `retrieve_password_key`
	 * exactly as a real admin-initiated password reset would. Tracking
	 * "just created" here lets `log_admin_password_reset_sent()` recognize
	 * that case and skip logging a redundant, misleadingly-worded
	 * "Password reset link sent" entry right under the creation entry.
	 *
	 * @var array<int, bool>
	 */
	protected $newly_created_user_ids = array();

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
	protected function register_hooks() {

		add_action( 'wp_login', $this->guarded( array( $this, 'log_login' ) ), 10, 2 );

		add_action( 'wp_logout', $this->guarded( array( $this, 'log_logout' ) ), 10, 1 );

		add_action( 'wp_login_failed', $this->guarded( array( $this, 'log_login_failed' ) ) );

		add_action( 'switch_to_user', $this->guarded( array( $this, 'log_switch_user' ) ), 10, 2 );

		add_action( 'switch_back_user', $this->guarded( array( $this, 'log_switch_back_user' ) ), 10, 2 );

		/*
		 * On Multisite, `wpmu_new_user` is the canonical creation hook: it fires
		 * for both Super Admin-created users and self-service network signups.
		 * `user_register` still fires underneath it in that case, so binding
		 * both here would double-log every Multisite user creation.
		 */
		if ( is_multisite() ) {
			add_action( 'wpmu_new_user', $this->guarded( array( $this, 'log_network_user_created' ) ) );
		} else {
			add_action( 'user_register', $this->guarded( array( $this, 'log_user_registered' ) ) );
		}

		add_action( 'profile_update', $this->guarded( array( $this, 'log_profile_updated' ) ), 10, 2 );

		add_action( 'set_user_role', $this->guarded( array( $this, 'log_role_changed' ) ), 10, 3 );

		add_action( 'delete_user', $this->guarded( array( $this, 'log_user_deleted' ) ), 10, 3 );

		add_action( 'after_password_reset', $this->guarded( array( $this, 'log_password_reset' ) ), 10, 2 );

		add_action( 'retrieve_password_key', $this->guarded( array( $this, 'log_admin_password_reset_sent' ) ), 10, 2 );

		add_action( 'granted_super_admin', $this->guarded( array( $this, 'log_super_admin_granted' ) ) );

		add_action( 'revoked_super_admin', $this->guarded( array( $this, 'log_super_admin_revoked' ) ) );

		add_action( 'add_user_to_blog', $this->guarded( array( $this, 'log_user_added_to_site' ) ), 10, 3 );

		add_action( 'remove_user_from_blog', $this->guarded( array( $this, 'log_user_removed_from_site' ) ), 10, 3 );

		add_filter( 'update_user_metadata', $this->guarded( array( $this, 'capture_user_meta_before_update' ) ), 10, 5 );

		add_action( 'added_user_meta', $this->guarded( array( $this, 'log_user_meta_added' ) ), 10, 4 );

		add_action( 'updated_user_meta', $this->guarded( array( $this, 'log_user_meta_updated' ) ), 10, 4 );

		add_action( 'deleted_user_meta', $this->guarded( array( $this, 'log_user_meta_deleted' ) ), 10, 4 );

		add_action(
			'wp_create_application_password',
			$this->guarded( array( $this, 'log_application_password_created' ) ),
			10,
			4
		);

		add_action(
			'wp_delete_application_password',
			$this->guarded( array( $this, 'log_application_password_deleted' ) ),
			10,
			2
		);
	}

	/**
	 * Log user login.
	 *
	 * @param string  $user_login Username.
	 * @param WP_User $user User object.
	 * @return void
	 */
	public function log_login( $user_login, $user ): void {
		/*
		 * `wp_login` fires right after `wp_set_auth_cookie()`, before
		 * WordPress updates the "current user" global for this request -
		 * `get_common_context()`'s default (`wp_get_current_user()`) would
		 * therefore record the *pre*-login anonymous state (id 0, no
		 * roles) instead of the user who just signed in. Pass `$user`
		 * explicitly so the actor recorded is correct.
		 */
		$this->insert_event_log(
			Events::AUTHENTICATION,
			Actions::LOGIN,
			array(
				'object_type' => 'user',
				'object_id'   => $user->ID,
				'user_id'     => $user->ID,
				'action'      => Actions::LOGIN,
				'message'     => sprintf(
					'User "%s" logged in.',
					$user_login
				),
				'context'     => $this->get_common_context( $user ),
			)
		);
	}

	/**
	 * Log logout.
	 *
	 * @param int $user_id User ID.
	 * @return void
	 */
	public function log_logout( $user_id = 0 ): void {

		$user_id = (int) $user_id;
		if ( $user_id <= 0 ) {
			return;
		}

		$user = get_userdata( $user_id );

		if ( ! $user ) {
			return;
		}

		/*
		 * `wp_logout` fires AFTER `wp_set_current_user( 0 )`, so by this
		 * point `get_common_context()`'s default would already record "no
		 * user" instead of the user who just logged out. Pass `$user`
		 * explicitly so the actor recorded is correct.
		 */
		$this->insert_event_log(
			Events::AUTHENTICATION,
			Actions::LOGOUT,
			array(
				'object_type' => 'user',
				'object_id'   => $user_id,
				'user_id'     => $user_id,
				'message'     => sprintf(
					'User "%s" logged out.',
					$user->user_login
				),
				'context'     => $this->get_common_context( $user ),
			)
		);
	}

	/**
	 * Log failed login.
	 *
	 * @param string $username Username.
	 * @return void
	 */
	public function log_login_failed( $username ): void {

		$matched_user = get_user_by( 'login', $username );

		$this->insert_event_log(
			Events::AUTHENTICATION,
			Actions::FAILED_LOGIN,
			array(
				'object_type' => 'user',
				'object_id'   => 0,
				'user_id'     => 0,
				'severity'    => Severity::WARNING,
				'message'     => sprintf(
					'Failed login attempt for "%s".',
					$username
				),
				'context'     => array_merge(
					$this->get_common_context(),
					array(
						'attempted_username'     => $username,
						'attempted_display_name' => $matched_user ? $matched_user->display_name : '',
					)
				),
			)
		);
	}

	/**
	 * Log switch to another user.
	 *
	 * Fired by the third-party "User Switching" plugin, not WordPress core.
	 *
	 * @param int $user_id User ID being switched to.
	 * @param int $old_user_id User ID being switched from.
	 * @return void
	 */
	public function log_switch_user( $user_id, $old_user_id ): void {

		$new_user = get_userdata( $user_id );
		$old_user = get_userdata( $old_user_id );

		if ( ! $new_user || ! $old_user ) {
			return;
		}

		$this->insert_event_log(
			Events::AUTHENTICATION,
			Actions::SWITCH_USER,
			array(
				'object_type' => 'user',
				'object_id'   => $new_user->ID,
				'user_id'     => $old_user->ID,
				'severity'    => Severity::WARNING,
				'message'     => sprintf(
					'User "%s" switched to user "%s".',
					$old_user->user_login,
					$new_user->user_login
				),
				'context'     => array_merge(
					$this->get_common_context(),
					array(
						'switched_from_user_id'    => $old_user->ID,
						'switched_from_user_login' => $old_user->user_login,
						'switched_to_roles'        => $new_user->roles,
					)
				),
			)
		);
	}

	/**
	 * Log switch back to the original user.
	 *
	 * Fired by the third-party "User Switching" plugin, not WordPress core.
	 *
	 * @param int $user_id User ID being switched back to.
	 * @param int $old_user_id User ID being switched away from.
	 * @return void
	 */
	public function log_switch_back_user( $user_id, $old_user_id ): void {

		$new_user = get_userdata( $user_id );
		$old_user = get_userdata( $old_user_id );

		if ( ! $new_user || ! $old_user ) {
			return;
		}

		$this->insert_event_log(
			Events::AUTHENTICATION,
			Actions::SWITCH_USER,
			array(
				'object_type' => 'user',
				'object_id'   => $new_user->ID,
				'user_id'     => $old_user->ID,
				'severity'    => Severity::WARNING,
				'message'     => sprintf(
					'User "%s" switched back to user "%s".',
					$old_user->user_login,
					$new_user->user_login
				),
				'context'     => array_merge(
					$this->get_common_context(),
					array(
						'switched_from_user_id'    => $old_user->ID,
						'switched_from_user_login' => $old_user->user_login,
						'switched_to_roles'        => $new_user->roles,
					)
				),
			)
		);
	}

	/**
	 * Log user registration on a single-site install.
	 *
	 * Distinguishes a visitor self-registering from an admin creating the
	 * account by whether there's a logged-in user driving the request.
	 *
	 * @param int $user_id User ID.
	 * @return void
	 */
	public function log_user_registered( $user_id ): void {

		$user = get_userdata( $user_id );

		if ( ! $user ) {
			return;
		}

		$this->newly_created_user_ids[ $user_id ] = true;

		$creator_id           = get_current_user_id();
		$is_self_registration = ( 0 === $creator_id );
		$role_label           = $this->format_role_list( $user->roles );

		$this->insert_event_log(
			Events::USER,
			$is_self_registration ? Actions::REGISTER : Actions::CREATE,
			array(
				'object_type' => 'user',
				'object_id'   => $user_id,
				'user_id'     => $creator_id,
				'message'     => $is_self_registration
					? sprintf( 'Visitor "%s" (%s) registered as a new user with role %s.', $user->user_login, $user->user_email, $role_label )
					: sprintf( 'New user "%s" (%s) created with role %s.', $user->user_login, $user->user_email, $role_label ),
				'after_data'  => wp_json_encode( $this->prepare_new_user_data( $user ) ),
				'context'     => $this->get_common_context(),
			)
		);
	}

	/**
	 * Log user creation on a Multisite network.
	 *
	 * Covers both a Super Admin creating a user from Network Admin and a
	 * visitor completing network signup activation.
	 *
	 * @param int $user_id User ID.
	 * @return void
	 */
	public function log_network_user_created( $user_id ): void {

		$user = get_userdata( $user_id );

		if ( ! $user ) {
			return;
		}

		$this->newly_created_user_ids[ $user_id ] = true;

		$is_admin_created = is_network_admin();
		$role_label       = $this->format_role_list( $user->roles );

		$this->insert_event_log(
			Events::USER,
			$is_admin_created ? Actions::CREATE : Actions::REGISTER,
			array(
				'object_type' => 'user',
				'object_id'   => $user_id,
				'user_id'     => get_current_user_id(),
				'message'     => $is_admin_created
					? sprintf( 'New network user "%s" (%s) created with role %s.', $user->user_login, $user->user_email, $role_label )
					: sprintf( 'User "%s" (%s) signed up on the network with role %s.', $user->user_login, $user->user_email, $role_label ),
				'after_data'  => wp_json_encode( $this->prepare_new_user_data( $user ) ),
				'context'     => $this->get_common_context(),
			)
		);
	}

	/**
	 * Format a user's roles for a log message, e.g. "administrator" or
	 * "editor, shop_manager" - or a fallback when a user somehow has none.
	 *
	 * @param string[] $roles Role slugs.
	 * @return string
	 */
	protected function format_role_list( array $roles ): string {

		return ! empty( $roles ) ? implode( ', ', $roles ) : __( 'no role', 'pastmark' );
	}

	/**
	 * Prepare newly-created user data for storage - the fields worth
	 * showing for "what was this account set up as", mirroring
	 * `diff_profile_fields()`'s field set plus email/role.
	 *
	 * @param WP_User $user Newly-created user.
	 * @return array
	 */
	protected function prepare_new_user_data( WP_User $user ): array {

		return array(
			'user_login' => $user->user_login,
			'user_email' => $user->user_email,
			'first_name' => $user->first_name,
			'last_name'  => $user->last_name,
			'role'       => $this->format_role_list( $user->roles ),
		);
	}

	/**
	 * Log profile update, including password and email changes.
	 *
	 * @param int     $user_id User ID.
	 * @param WP_User $old_user_data Old user data.
	 * @return void
	 */
	public function log_profile_updated( $user_id, $old_user_data ): void {

		$user = get_userdata( $user_id );

		if ( ! $user ) {
			return;
		}

		if ( ! $old_user_data instanceof WP_User ) {
			// No prior snapshot to diff against (a caller invoked
			// `profile_update` directly, bypassing `wp_update_user()`) -
			// there's no way to tell what changed, so fall back to a
			// bare notice rather than silently dropping the event.
			$this->insert_event_log(
				Events::USER,
				Actions::UPDATE,
				array(
					'object_type' => 'user',
					'object_id'   => $user_id,
					'user_id'     => get_current_user_id(),
					'message'     => sprintf( 'Profile updated for "%s".', $user->user_login ),
					'context'     => $this->get_common_context(),
				)
			);
			return;
		}

		$password_changed = ( $old_user_data->user_pass !== $user->user_pass );
		$email_changed    = ( $old_user_data->user_email !== $user->user_email );

		$changes = $this->diff_profile_fields( $user_id, $user, $old_user_data );

		/*
		 * `profile_update` also fires for changes that have nothing to do
		 * with an actual profile edit - most notably WordPress saving the
		 * password-reset activation key hash on the user row whenever a
		 * "set your password"/reset email is generated (see
		 * `get_password_reset_key()`). If none of the fields this logger
		 * tracks (or email/password) actually changed, this firing is
		 * WordPress's own internal bookkeeping, not a real edit - skip it
		 * rather than logging a "profile updated" entry with nothing behind it.
		 */
		if ( empty( $changes['labels'] ) && ! $password_changed && ! $email_changed ) {
			return;
		}

		if ( ! empty( $changes['labels'] ) ) {

			$this->insert_event_log(
				Events::USER,
				Actions::UPDATE,
				array(
					'object_type' => 'user',
					'object_id'   => $user_id,
					'user_id'     => get_current_user_id(),
					'message'     => sprintf(
						'Profile updated for "%s". Changed: %s.',
						$user->user_login,
						implode( ', ', $changes['labels'] )
					),
					'before_data' => wp_json_encode( $changes['before'] ),
					'after_data'  => wp_json_encode( $changes['after'] ),
					'context'     => $this->get_common_context(),
				)
			);
		}

		if ( $password_changed ) {
			$this->log_password_changed( $user );
		}

		if ( $email_changed ) {
			$this->log_email_changed( $user, $old_user_data->user_email );
		}
	}

	/**
	 * Diff the standard profile fields (name, nickname, bio, website)
	 * between their pre-update and post-update values.
	 *
	 * Deliberately excludes email and password: those already get their
	 * own dedicated, higher-severity log entry (`log_email_changed()`,
	 * `log_password_changed()`) since they're security-sensitive rather
	 * than cosmetic.
	 *
	 * @param int     $user_id User ID.
	 * @param WP_User $user Freshly-loaded user, reflecting the update.
	 * @param WP_User $old_user_data Pre-update snapshot handed to `profile_update`.
	 * @return array{before: array<string, mixed>, after: array<string, mixed>, labels: string[]}
	 */
	protected function diff_profile_fields( int $user_id, WP_User $user, WP_User $old_user_data ): array {

		$before = array();
		$after  = array();
		$labels = array();

		foreach ( self::TRACKED_PROFILE_CORE_FIELDS as $field => $label ) {

			$old_value = $old_user_data->$field ?? '';
			$new_value = $user->$field ?? '';

			if ( $old_value === $new_value ) {
				continue;
			}

			$before[ $field ] = $old_value;
			$after[ $field ]  = $new_value;
			$labels[]         = $label;
		}

		foreach ( self::TRACKED_PROFILE_META_FIELDS as $field => $label ) {

			$pending_key = $user_id . ':' . $field;

			if ( ! array_key_exists( $pending_key, $this->pending_profile_field_values ) ) {
				continue;
			}

			$old_value = $this->pending_profile_field_values[ $pending_key ];
			unset( $this->pending_profile_field_values[ $pending_key ] );

			$new_value = get_user_meta( $user_id, $field, true );

			if ( $old_value === $new_value ) {
				continue;
			}

			$before[ $field ] = $old_value;
			$after[ $field ]  = $new_value;
			$labels[]         = $label;
		}

		return array(
			'before' => $before,
			'after'  => $after,
			'labels' => $labels,
		);
	}

	/**
	 * Log a password change made through the profile update flow.
	 *
	 * @param WP_User $user User whose password changed.
	 * @return void
	 */
	protected function log_password_changed( WP_User $user ): void {

		$is_self = ( (int) get_current_user_id() === (int) $user->ID );
		$actor   = wp_get_current_user();

		$this->insert_event_log(
			Events::USER,
			Actions::PASSWORD_CHANGE,
			array(
				'object_type' => 'user',
				'object_id'   => $user->ID,
				'user_id'     => get_current_user_id(),
				'severity'    => Severity::WARNING,
				'message'     => $is_self
					? sprintf( 'User "%s" changed their own password.', $user->user_login )
					: sprintf( 'Password changed for user "%s" by "%s".', $user->user_login, $actor->user_login ),
				'context'     => $this->get_common_context(),
			)
		);
	}

	/**
	 * Log an email address change made through the profile update flow.
	 *
	 * @param WP_User $user User whose email changed.
	 * @param string  $old_email Previous email address.
	 * @return void
	 */
	protected function log_email_changed( WP_User $user, string $old_email ): void {

		$is_self = ( (int) get_current_user_id() === (int) $user->ID );
		$actor   = wp_get_current_user();

		$this->insert_event_log(
			Events::USER,
			Actions::EMAIL_CHANGE,
			array(
				'object_type' => 'user',
				'object_id'   => $user->ID,
				'user_id'     => get_current_user_id(),
				'severity'    => Severity::WARNING,
				'message'     => $is_self
					? sprintf( 'User "%s" changed their own email address.', $user->user_login )
					: sprintf( 'Email address changed for user "%s" by "%s".', $user->user_login, $actor->user_login ),
				'before_data' => wp_json_encode( array( 'user_email' => $old_email ) ),
				'after_data'  => wp_json_encode( array( 'user_email' => $user->user_email ) ),
				'context'     => $this->get_common_context(),
			)
		);
	}

	/**
	 * Log role change.
	 *
	 * @param int    $user_id User ID.
	 * @param string $role New role.
	 * @param array  $old_roles Old roles.
	 * @return void
	 */
	public function log_role_changed( $user_id, $role, $old_roles ): void {
		/*
		 * `WP_User::set_role()` fires this same hook when a role is
		 * assigned to a brand-new user during `wp_insert_user()` - in
		 * that case `$old_roles` is always empty, since a just-inserted
		 * user has no prior roles to change FROM. That's not a "role
		 * change", it's the account being set up with its first role
		 * (already shown in the creation entry) - only log an actual
		 * change away from a previous role.
		 */
		if ( empty( $old_roles ) ) {
			return;
		}

		$user = get_userdata( $user_id );

		if ( ! $user ) {
			return;
		}

		$this->insert_event_log(
			Events::USER,
			Actions::ROLE_CHANGE,
			array(
				'object_type' => 'user',
				'object_id'   => $user_id,
				'user_id'     => get_current_user_id(),
				'message'     => sprintf(
					'Role updated for "%s".',
					$user->user_login
				),
				'context'     => array_merge(
					$this->get_common_context(),
					array(
						'old_roles' => $old_roles,
						'new_role'  => $role,
					)
				),
			)
		);
	}

	/**
	 * Log user deletion.
	 *
	 * @param int      $id User ID being deleted.
	 * @param int|null $reassign ID of the user posts are reassigned to.
	 * @param WP_User  $user User object being deleted.
	 * @return void
	 */
	public function log_user_deleted( $id, $reassign, $user ): void {

		if ( ! $user instanceof WP_User ) {
			return;
		}

		$actor = wp_get_current_user();

		$this->insert_event_log(
			Events::USER,
			Actions::DELETE,
			array(
				'object_type' => 'user',
				'object_id'   => $id,
				'user_id'     => get_current_user_id(),
				'severity'    => Severity::WARNING,
				'message'     => sprintf(
					'User "%s" deleted by "%s".',
					$user->user_login,
					( $actor && $actor->exists() ) ? $actor->user_login : 'system'
				),
				'before_data' => wp_json_encode(
					array(
						'user_login' => $user->user_login,
						'user_email' => $user->user_email,
						'roles'      => $user->roles,
					)
				),
				'context'     => array_merge(
					$this->get_common_context(),
					array(
						'reassign' => $reassign ? (int) $reassign : null,
					)
				),
			)
		);
	}

	/**
	 * Log a self-service password reset completed via the emailed reset link.
	 *
	 * @param WP_User $user User whose password was reset.
	 * @param string  $new_pass New plaintext password (not logged).
	 * @return void
	 */
	public function log_password_reset( $user, $new_pass ): void {

		if ( ! $user instanceof WP_User ) {
			return;
		}

		$this->insert_event_log(
			Events::USER,
			Actions::PASSWORD_CHANGE,
			array(
				'object_type' => 'user',
				'object_id'   => $user->ID,
				'user_id'     => $user->ID,
				'severity'    => Severity::WARNING,
				'message'     => sprintf(
					'User "%s" reset their password.',
					$user->user_login
				),
				'context'     => $this->get_common_context(),
			)
		);
	}

	/**
	 * Log an admin sending a password reset link to another user.
	 *
	 * Skips the front-end "lost password" flow, which fires the same hook
	 * when a visitor requests a reset for their own account - and skips a
	 * user just created in this same request, since the "set your
	 * password" email WordPress sends for a brand-new account (when
	 * "Send User Notification" is checked) generates its activation link
	 * through this exact same mechanism. That's part of creating the
	 * account, not a separate reset of an existing password - already
	 * implied by the creation entry, so logging it again here would just
	 * be confusing, misleadingly-worded noise right under it.
	 *
	 * @param string $user_login Username the reset key was generated for.
	 * @param string $key Reset key (not logged).
	 * @return void
	 */
	public function log_admin_password_reset_sent( $user_login, $key ): void {

		$actor_id = get_current_user_id();

		if ( ! $actor_id || ! user_can( $actor_id, 'edit_users' ) ) {
			return;
		}

		$target = get_user_by( 'login', $user_login );

		if ( ! $target || (int) $target->ID === $actor_id ) {
			return;
		}

		if ( isset( $this->newly_created_user_ids[ $target->ID ] ) ) {
			return;
		}

		$actor = wp_get_current_user();

		$this->insert_event_log(
			Events::USER,
			Actions::PASSWORD_RESET_SENT,
			array(
				'object_type' => 'user',
				'object_id'   => $target->ID,
				'user_id'     => $actor_id,
				'message'     => sprintf(
					'Password reset link sent to "%s" by "%s".',
					$target->user_login,
					$actor->user_login
				),
				'context'     => $this->get_common_context(),
			)
		);
	}

	/**
	 * Log Super Admin privileges granted (Multisite only).
	 *
	 * @param int $user_id User ID.
	 * @return void
	 */
	public function log_super_admin_granted( $user_id ): void {

		$user = get_userdata( $user_id );

		if ( ! $user ) {
			return;
		}

		$this->insert_event_log(
			Events::USER,
			Actions::SUPER_ADMIN_CHANGE,
			array(
				'object_type' => 'user',
				'object_id'   => $user_id,
				'user_id'     => get_current_user_id(),
				'severity'    => Severity::WARNING,
				'message'     => sprintf(
					'User "%s" granted Super Admin privileges.',
					$user->user_login
				),
				'before_data' => wp_json_encode( array( 'super_admin' => false ) ),
				'after_data'  => wp_json_encode( array( 'super_admin' => true ) ),
				'context'     => $this->get_common_context(),
			)
		);
	}

	/**
	 * Log Super Admin privileges revoked (Multisite only).
	 *
	 * @param int $user_id User ID.
	 * @return void
	 */
	public function log_super_admin_revoked( $user_id ): void {

		$user = get_userdata( $user_id );

		if ( ! $user ) {
			return;
		}

		$this->insert_event_log(
			Events::USER,
			Actions::SUPER_ADMIN_CHANGE,
			array(
				'object_type' => 'user',
				'object_id'   => $user_id,
				'user_id'     => get_current_user_id(),
				'severity'    => Severity::WARNING,
				'message'     => sprintf(
					'Super Admin privileges revoked for user "%s".',
					$user->user_login
				),
				'before_data' => wp_json_encode( array( 'super_admin' => true ) ),
				'after_data'  => wp_json_encode( array( 'super_admin' => false ) ),
				'context'     => $this->get_common_context(),
			)
		);
	}

	/**
	 * Log an existing user being added to a site (Multisite only).
	 *
	 * @param int    $user_id User ID.
	 * @param string $role Role assigned on the site.
	 * @param int    $blog_id Site ID.
	 * @return void
	 */
	public function log_user_added_to_site( $user_id, $role, $blog_id ): void {

		$user = get_userdata( $user_id );

		if ( ! $user ) {
			return;
		}

		$site = get_blog_details( $blog_id );

		$this->insert_event_log(
			Events::USER,
			Actions::ADD_TO_SITE,
			array(
				'object_type' => 'user',
				'object_id'   => $user_id,
				'user_id'     => get_current_user_id(),
				'message'     => sprintf(
					'User "%s" added to site "%s" as %s.',
					$user->user_login,
					$site ? $site->blogname : $blog_id,
					$role
				),
				'context'     => array_merge(
					$this->get_common_context(),
					array(
						'blog_id' => (int) $blog_id,
						'role'    => $role,
					)
				),
			)
		);
	}

	/**
	 * Log a user being removed from a site (Multisite only).
	 *
	 * @param int      $user_id User ID.
	 * @param int      $blog_id Site ID.
	 * @param int|bool $reassign ID of the user content is reassigned to, or false.
	 * @return void
	 */
	public function log_user_removed_from_site( $user_id, $blog_id, $reassign ): void {

		$user = get_userdata( $user_id );

		if ( ! $user ) {
			return;
		}

		$site = get_blog_details( $blog_id );

		$this->insert_event_log(
			Events::USER,
			Actions::REMOVE_FROM_SITE,
			array(
				'object_type' => 'user',
				'object_id'   => $user_id,
				'user_id'     => get_current_user_id(),
				'severity'    => Severity::WARNING,
				'message'     => sprintf(
					'User "%s" removed from site "%s".',
					$user->user_login,
					$site ? $site->blogname : $blog_id
				),
				'context'     => array_merge(
					$this->get_common_context(),
					array(
						'blog_id'  => (int) $blog_id,
						'reassign' => $reassign ? (int) $reassign : null,
					)
				),
			)
		);
	}

	/**
	 * Capture a user meta value before it's overwritten, for diffing.
	 *
	 * Must return `$check` unmodified so the actual meta save isn't
	 * short-circuited.
	 *
	 * @param mixed  $check Whether to short-circuit the meta update.
	 * @param int    $object_id User ID.
	 * @param string $meta_key Meta key.
	 * @param mixed  $meta_value New meta value.
	 * @param mixed  $prev_value Previous value to match, if any.
	 * @return mixed
	 */
	public function capture_user_meta_before_update( $check, $object_id, $meta_key, $meta_value, $prev_value ) {

		if ( array_key_exists( $meta_key, self::TRACKED_PROFILE_META_FIELDS ) ) {
			$this->pending_profile_field_values[ $object_id . ':' . $meta_key ] = get_user_meta( $object_id, $meta_key, true );
		}

		if ( $this->is_reserved_user_meta_key( $meta_key ) ) {
			return $check;
		}

		$this->pending_user_meta_values[ $object_id . ':' . $meta_key ] = get_user_meta( $object_id, $meta_key, true );

		return $check;
	}

	/**
	 * Log a custom user meta field being added.
	 *
	 * @param int    $mid Meta ID.
	 * @param int    $user_id User ID.
	 * @param string $meta_key Meta key.
	 * @param mixed  $meta_value Meta value.
	 * @return void
	 */
	public function log_user_meta_added( $mid, $user_id, $meta_key, $meta_value ): void {

		if ( $this->is_reserved_user_meta_key( $meta_key ) ) {
			return;
		}

		$user = get_userdata( $user_id );

		if ( ! $user ) {
			return;
		}

		$this->insert_event_log(
			Events::USER,
			Actions::USER_META_ADD,
			array(
				'object_type' => 'user',
				'object_id'   => $user_id,
				'user_id'     => get_current_user_id(),
				'message'     => sprintf(
					'Custom field "%s" added for user "%s".',
					$meta_key,
					$user->user_login
				),
				'after_data'  => wp_json_encode( array( $meta_key => $meta_value ) ),
				'context'     => array_merge(
					$this->get_common_context(),
					array( 'user_meta_key' => $meta_key )
				),
			)
		);
	}

	/**
	 * Log a custom user meta field being updated.
	 *
	 * @param int    $meta_id Meta ID.
	 * @param int    $user_id User ID.
	 * @param string $meta_key Meta key.
	 * @param mixed  $meta_value New meta value.
	 * @return void
	 */
	public function log_user_meta_updated( $meta_id, $user_id, $meta_key, $meta_value ): void {

		if ( $this->is_reserved_user_meta_key( $meta_key ) ) {
			return;
		}

		$pending_key = $user_id . ':' . $meta_key;

		$old_value = $this->pending_user_meta_values[ $pending_key ] ?? '';

		unset( $this->pending_user_meta_values[ $pending_key ] );

		if ( $old_value === $meta_value ) {
			return;
		}

		$user = get_userdata( $user_id );

		if ( ! $user ) {
			return;
		}

		$this->insert_event_log(
			Events::USER,
			Actions::USER_META_UPDATE,
			array(
				'object_type' => 'user',
				'object_id'   => $user_id,
				'user_id'     => get_current_user_id(),
				'message'     => sprintf(
					'Custom field "%s" updated for user "%s".',
					$meta_key,
					$user->user_login
				),
				'before_data' => wp_json_encode( array( $meta_key => $old_value ) ),
				'after_data'  => wp_json_encode( array( $meta_key => $meta_value ) ),
				'context'     => array_merge(
					$this->get_common_context(),
					array( 'user_meta_key' => $meta_key )
				),
			)
		);
	}

	/**
	 * Log a custom user meta field being deleted.
	 *
	 * @param array  $meta_ids Deleted meta IDs.
	 * @param int    $user_id User ID.
	 * @param string $meta_key Meta key.
	 * @param mixed  $meta_value Deleted value.
	 * @return void
	 */
	public function log_user_meta_deleted( $meta_ids, $user_id, $meta_key, $meta_value ): void {

		if ( '' === $meta_key || $this->is_reserved_user_meta_key( $meta_key ) ) {
			return;
		}

		$user = get_userdata( $user_id );

		if ( ! $user ) {
			return;
		}

		$this->insert_event_log(
			Events::USER,
			Actions::USER_META_DELETE,
			array(
				'object_type' => 'user',
				'object_id'   => $user_id,
				'user_id'     => get_current_user_id(),
				'message'     => sprintf(
					'Custom field "%s" deleted for user "%s".',
					$meta_key,
					$user->user_login
				),
				'before_data' => wp_json_encode( array( $meta_key => $meta_value ) ),
				'context'     => array_merge(
					$this->get_common_context(),
					array( 'user_meta_key' => $meta_key )
				),
			)
		);
	}

	/**
	 * Check whether a user meta key is WordPress-internal noise that
	 * shouldn't be logged as a "custom field" (session data, per-screen
	 * admin UI preferences, etc.).
	 *
	 * Also covers `TRACKED_PROFILE_META_FIELDS` (first_name, last_name,
	 * nickname, description) - those aren't noise, but they already get
	 * surfaced as part of the profile-update diff in `log_profile_updated()`
	 * via `diff_profile_fields()`, so logging them again here would just
	 * duplicate that as a separate "custom field updated" entry.
	 *
	 * @param string $meta_key Meta key.
	 * @return bool
	 */
	protected function is_reserved_user_meta_key( string $meta_key ): bool {

		global $wpdb;

		$reserved_exact = array(
			'session_tokens',
			'_application_passwords',
			'nickname',
			'first_name',
			'last_name',
			'description',
			'rich_editing',
			'syntax_highlighting',
			'comment_shortcuts',
			'admin_color',
			'use_ssl',
			'show_admin_bar_front',
			'show_admin_bar_admin',
			'locale',
			'community-events-location',
			'wp_persisted_preferences',
			$wpdb->prefix . 'capabilities',
			$wpdb->prefix . 'user_level',
			$wpdb->prefix . 'user-settings',
			$wpdb->prefix . 'user-settings-time',
			$wpdb->prefix . 'dashboard_quick_press_last_post_id',
		);

		if ( in_array( $meta_key, $reserved_exact, true ) ) {
			return true;
		}

		$reserved_prefixes = array(
			'closedpostboxes_',
			'metaboxhidden_',
			'meta-box-order_',
			'screen_layout_',
		);

		foreach ( $reserved_prefixes as $prefix ) {

			if ( 0 === strpos( $meta_key, $prefix ) ) {
				return true;
			}
		}

		$reserved_suffixes = array(
			'columnshidden',
			'_per_page',
		);

		foreach ( $reserved_suffixes as $suffix ) {

			if ( strlen( $meta_key ) >= strlen( $suffix ) && substr( $meta_key, -strlen( $suffix ) ) === $suffix ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Log an application password being created.
	 *
	 * @param int    $user_id User ID.
	 * @param array  $new_item Details about the created password.
	 * @param string $new_password Generated plaintext password (not logged).
	 * @param array  $args Arguments used to create the password.
	 * @return void
	 */
	public function log_application_password_created( $user_id, $new_item, $new_password, $args ): void {

		$user = get_userdata( $user_id );

		if ( ! $user ) {
			return;
		}

		$name = $new_item['name'] ?? '';

		$this->insert_event_log(
			Events::USER,
			Actions::APP_PASSWORD_CREATE,
			array(
				'object_type' => 'user',
				'object_id'   => $user_id,
				'user_id'     => get_current_user_id(),
				'severity'    => Severity::WARNING,
				'message'     => sprintf(
					'Application password "%s" created for user "%s".',
					$name,
					$user->user_login
				),
				'after_data'  => wp_json_encode(
					array(
						'name' => $name,
						'uuid' => $new_item['uuid'] ?? '',
					)
				),
				'context'     => $this->get_common_context(),
			)
		);
	}

	/**
	 * Log an application password being revoked.
	 *
	 * @param int   $user_id User ID.
	 * @param array $item Details about the revoked password.
	 * @return void
	 */
	public function log_application_password_deleted( $user_id, $item ): void {

		$user = get_userdata( $user_id );

		if ( ! $user ) {
			return;
		}

		$name = $item['name'] ?? '';

		$this->insert_event_log(
			Events::USER,
			Actions::APP_PASSWORD_REVOKE,
			array(
				'object_type' => 'user',
				'object_id'   => $user_id,
				'user_id'     => get_current_user_id(),
				'severity'    => Severity::WARNING,
				'message'     => sprintf(
					'Application password "%s" revoked for user "%s".',
					$name,
					$user->user_login
				),
				'before_data' => wp_json_encode(
					array(
						'name' => $name,
						'uuid' => $item['uuid'] ?? '',
					)
				),
				'context'     => $this->get_common_context(),
			)
		);
	}
}
