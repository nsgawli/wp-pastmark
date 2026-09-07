<?php // phpcs:ignore WordPress.Files.FileName.NotHyphenatedLowercase

namespace Pastmark\ActivityLoggers\UltimateMember;

use Pastmark\ActivityLoggers\AbstractLogger;
use Pastmark\Constants\Actions;
use Pastmark\Constants\Events;
use Pastmark\Constants\Severity;

defined( 'ABSPATH' ) || exit;

/**
 * Logs a new member registering through Ultimate Member (PM-174, Sprint 12).
 *
 * Ultimate Member does not model membership as a custom post type - a
 * member is a real `WP_User`, and registration ultimately still runs
 * through WordPress' own `wp_insert_user()` under the hood. Core
 * `UserActivityLogger` already logs every new user via `user_register`
 * (`Events::USER`), so this logger deliberately does **not** try to
 * suppress that - the two events serve different purposes (one generic
 * "a user account was created", one UM-specific "a member registered
 * through form X") and coexist the same way, e.g., a WooCommerce order
 * and its underlying `wp_insert_post()` call both get their own event.
 * Only Ultimate Member's own *profile-save* path (`ProfileActivityLogger`)
 * has a genuine duplicate-logging problem worth fixing - see that class'
 * own docblock. Registration itself also produces one or two extra core
 * `user`/`update` ("Changed: Display Name") rows - live-verified as UM's
 * own internal registration bookkeeping (`um_registration_set_profile_
 * full_name()`/`um_check_user_status()`, both hooked to actions in this
 * same `um_after_insert_user()` chain), not this logger's own profile-edit
 * path - deliberately left alone for the same "different purpose, allowed
 * to coexist" reason as the `user_register` dual-log above.
 *
 * Hooks `um_registration_complete( $user_id, $submitted_data, $form_data )`
 * - fired once per registration, front-end self-service or an admin
 * creating a member from wp-admin's "Add New User" screen with a UM role
 * selected - confirmed against the real installed source (Ultimate Member
 * 2.13.0, this dev environment; `includes/core/um-actions-register.php`).
 * `um_user_register` (the same file) fires slightly earlier from the same
 * `um_after_insert_user()` function and carries identical arguments; this
 * logger uses `um_registration_complete` since it's the one UM's own docs
 * describe as "fires after complete UM user registration".
 */
class RegistrationActivityLogger extends AbstractLogger {

	/**
	 * {@inheritDoc}
	 *
	 * @var string
	 */
	protected $integration = 'ultimate-member';

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

		add_action( 'um_registration_complete', $this->guarded( array( $this, 'log_registered' ) ), 10, 3 );
	}

	/**
	 * Declare the shared `ultimate-member` event group - all three of this
	 * integration's loggers' actions live here; only this logger (listed
	 * first in `UltimateMemberIntegration::get_logger_classes()`)
	 * registers it, matching `BbPressIntegration`'s existing convention.
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

		$events[ Events::ULTIMATE_MEMBER ] = array(
			'label'   => __( 'Ultimate Member', 'pastmark' ),
			'source'  => 'ultimate-member',
			'actions' => array(
				array(
					'key'            => Actions::MEMBER_REGISTER,
					'label'          => __( 'Member Register', 'pastmark' ),
					'description'    => __( 'A new member registered through an Ultimate Member form.', 'pastmark' ),
					'severity'       => Severity::INFO,
					'severity_label' => Severity::resolve_label( Severity::INFO ),
				),
				array(
					'key'            => Actions::MEMBER_PROFILE_UPDATE,
					'label'          => __( 'Member Profile Update', 'pastmark' ),
					'description'    => __( "A member's Ultimate Member profile fields changed.", 'pastmark' ),
					'severity'       => Severity::INFO,
					'severity_label' => Severity::resolve_label( Severity::INFO ),
				),
				array(
					'key'            => Actions::MEMBER_ROLE_CHANGE,
					'label'          => __( 'Member Role Change', 'pastmark' ),
					'description'    => __( "A member's Ultimate Member role assignment was changed by an admin.", 'pastmark' ),
					'severity'       => Severity::WARNING,
					'severity_label' => Severity::resolve_label( Severity::WARNING ),
				),
			),
		);

		return $events;
	}

	/**
	 * Log a new member registering.
	 *
	 * @param int        $user_id        Newly-registered user ID.
	 * @param array      $submitted_data $_POST submission array (unused - contains raw, unsanitized form input).
	 * @param array|null $form_data      UM form data - `null` when a member was created from wp-admin's "Add New User" screen rather than a front-end UM form.
	 * @return void
	 */
	public function log_registered( $user_id, $submitted_data, $form_data ): void {

		$user = get_userdata( $user_id );

		if ( ! $user ) {
			return;
		}

		$creator_id      = get_current_user_id();
		$is_self_service = ( 0 === $creator_id );

		$form_name = $this->get_form_name( $form_data );

		$this->insert_event_log(
			Events::ULTIMATE_MEMBER,
			Actions::MEMBER_REGISTER,
			array(
				'object_type' => 'user',
				'object_id'   => $user_id,
				'user_id'     => $creator_id,
				'severity'    => Severity::INFO,
				'message'     => $is_self_service
					? sprintf(
						'Member "%s" (%s) registered via the Ultimate Member form "%s".',
						$user->user_login,
						$user->user_email,
						$form_name
					)
					: sprintf(
						'Member "%s" (%s) was created%s by an administrator.',
						$user->user_login,
						$user->user_email,
						$form_name ? sprintf( ' via the Ultimate Member form "%s"', $form_name ) : ''
					),
				'after_data'  => wp_json_encode(
					array(
						'user_login' => $user->user_login,
						'user_email' => $user->user_email,
						'role'       => ! empty( $user->roles ) ? implode( ', ', $user->roles ) : __( 'no role', 'pastmark' ),
					)
				),
				'context'     => array_merge(
					$this->get_common_context(),
					array(
						'form_id'   => is_array( $form_data ) ? ( $form_data['form_id'] ?? 0 ) : 0,
						'form_name' => $form_name,
					)
				),
			)
		);
	}

	/**
	 * Resolve a UM registration form's display name from the `$form_data`
	 * array a registration hook hands over. UM registration forms are
	 * themselves a `um_form` custom post type, so `form_id` is a real post
	 * ID - `get_the_title()` is the same lookup UM's own admin screens use.
	 *
	 * @param array|null $form_data UM form data, or `null` (admin-created member, no front-end form involved).
	 * @return string Form title, or '' when there's no form to attribute (admin-created member).
	 */
	protected function get_form_name( $form_data ): string {

		if ( ! is_array( $form_data ) || empty( $form_data['form_id'] ) ) {
			return '';
		}

		$title = get_the_title( (int) $form_data['form_id'] );

		return '' !== $title ? $title : sprintf( '#%d', (int) $form_data['form_id'] );
	}
}
