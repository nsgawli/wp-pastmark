<?php // phpcs:ignore WordPress.Files.FileName.NotHyphenatedLowercase

namespace Pastmark\Dashboard;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Tracks, per user, when they last viewed a Pastmark dashboard surface
 * (the native `wp_dashboard_setup` widget, `DashboardWidget`, and/or the
 * React dashboard behind `GET /pastmark/v1/dashboard`), so both surfaces
 * can tell an admin which log entries are new since their last visit.
 *
 * Stored as user meta rather than a single option — "last viewed" is
 * inherently per-admin, not site-wide, so a shared option would show every
 * admin the same "new since" state regardless of when *they* individually
 * last looked. `uninstall.php` explicitly cleans this meta key up for all
 * users (there being no existing per-user-meta cleanup routine in this
 * codebase to piggyback on).
 */
class DashboardLastViewed {

	/**
	 * User meta key the last-viewed timestamp is stored under.
	 */
	const META_KEY = 'pastmark_dashboard_last_viewed';

	/**
	 * Get the timestamp a user last viewed the dashboard, in the same
	 * `current_time( 'mysql', true )` (GMT) shape as `wp_pastmark_logs.timestamp`
	 * so callers can compare the two directly with a plain string comparison
	 * (MySQL datetime strings sort/compare correctly as strings).
	 *
	 * @param int $user_id User ID. Defaults to the current user.
	 * @return string GMT `Y-m-d H:i:s` timestamp, or '' if never recorded
	 *                (e.g. this admin's first-ever dashboard visit).
	 */
	public static function get( int $user_id = 0 ): string {

		$user_id = $user_id ? $user_id : get_current_user_id();

		if ( ! $user_id ) {
			return '';
		}

		return (string) get_user_meta( $user_id, self::META_KEY, true );
	}

	/**
	 * Record that a user is viewing the dashboard right now.
	 *
	 * Callers that also render a "new since last visit" state (both
	 * `DashboardWidget::render_widget()` and the React dashboard, once
	 * PM-145 wires it up) must call `get()` *before* calling this — this
	 * overwrites the very value that state was computed against.
	 *
	 * @param int $user_id User ID. Defaults to the current user.
	 * @return string The new GMT timestamp that was stored, or '' if there
	 *                was no valid user to record against.
	 */
	public static function mark_viewed( int $user_id = 0 ): string {

		$user_id = $user_id ? $user_id : get_current_user_id();

		if ( ! $user_id ) {
			return '';
		}

		$now = current_time( 'mysql', true );

		update_user_meta( $user_id, self::META_KEY, $now );

		return $now;
	}
}
