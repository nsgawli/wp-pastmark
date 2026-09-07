<?php // phpcs:ignore WordPress.Files.FileName.NotHyphenatedLowercase

namespace Pastmark\Utils;

defined( 'ABSPATH' ) || exit;

/**
 * Tracks failed-login attempts per username+IP pair in a rolling time
 * window, so `UserActivityLogger::log_login_failed()` can collapse a
 * brute-force burst into a small, bounded number of rows instead of
 * inserting one row per attempt.
 *
 * State lives in a transient (one per username+IP pair) rather than a DB
 * table of its own — this is a short-lived rate-limiting counter, not part
 * of the permanent activity record, so it doesn't belong in
 * `wp_pastmark_logs`.
 *
 * Window/threshold are exposed as filters for now (hardcoded defaults);
 * `Installation\Settings\Security` (added alongside this) reads its stored
 * option values and applies them via these same filters, so nothing here
 * needs to change once that settings UI exists.
 */
class LoginThrottle {

	/**
	 * Transient key prefix.
	 */
	const TRANSIENT_PREFIX = 'pastmark_login_throttle_';

	/**
	 * Default rolling window, in seconds.
	 */
	const DEFAULT_WINDOW = 300;

	/**
	 * Default number of attempts logged individually before throttling
	 * kicks in.
	 */
	const DEFAULT_MAX_ATTEMPTS = 5;

	/**
	 * Rolling window length, in seconds.
	 *
	 * Reads `pastmark_security_settings['loginThrottleWindow']` (Security
	 * settings tab, PM-137) when present, falling back to the built-in
	 * default otherwise — read directly via `get_option()` rather than
	 * importing `Installation\Settings\Security`, matching `ExcludeHelper`'s
	 * existing decoupled-from-the-installer convention. The PHP filter
	 * remains available on top for a site-specific override that doesn't
	 * go through the Settings UI at all.
	 *
	 * @return int
	 */
	public static function get_window(): int {

		$settings = get_option( 'pastmark_security_settings', array() );
		$default  = isset( $settings['loginThrottleWindow'] ) ? (int) $settings['loginThrottleWindow'] : self::DEFAULT_WINDOW;

		/**
		 * Filters the failed-login throttle window, in seconds.
		 *
		 * @param int $window Default window (seconds) — the stored Security
		 *                    setting when present, otherwise the built-in default.
		 */
		$window = (int) apply_filters( 'pastmark_login_throttle_window', $default );

		return max( 1, $window );
	}

	/**
	 * Number of failed attempts (per username+IP pair) logged individually
	 * before further attempts in the same window get collapsed into one
	 * updating summary row.
	 *
	 * Reads `pastmark_security_settings['loginThrottleMax']` the same way
	 * `get_window()` reads `loginThrottleWindow` — see there for why.
	 *
	 * @return int
	 */
	public static function get_max_attempts(): int {

		$settings = get_option( 'pastmark_security_settings', array() );
		$default  = isset( $settings['loginThrottleMax'] ) ? (int) $settings['loginThrottleMax'] : self::DEFAULT_MAX_ATTEMPTS;

		/**
		 * Filters the failed-login throttle threshold (attempts per window).
		 *
		 * @param int $max_attempts Default threshold — the stored Security
		 *                          setting when present, otherwise the built-in default.
		 */
		$max_attempts = (int) apply_filters( 'pastmark_login_throttle_max_attempts', $default );

		return max( 1, $max_attempts );
	}

	/**
	 * Record one failed-login attempt and decide what the caller should do
	 * about it.
	 *
	 * @param string $username Attempted username, as given to `wp_login_failed`.
	 * @param string $ip       Requesting IP address.
	 * @return array {
	 *     @type string   $action  One of:
	 *                             - `log`: attempt count is still within the
	 *                               threshold (or the window just reset) —
	 *                               log a normal, individual row as before.
	 *                             - `summarize`: threshold just crossed on
	 *                               this attempt — insert one new summary
	 *                               row and register its ID via
	 *                               `remember_row_id()`.
	 *                             - `update`: already past the threshold and
	 *                               a summary row is already known — update
	 *                               that row's count instead of inserting.
	 *     @type int      $count   Total attempts recorded in the current window,
	 *                             including this one.
	 *     @type int|null $row_id  The previously-registered summary row ID,
	 *                             only present when `$action` is `update`.
	 * }
	 */
	public static function record_attempt( string $username, string $ip ): array {

		$key   = self::transient_key( $username, $ip );
		$now   = time();
		$state = get_transient( $key );

		if ( ! is_array( $state ) || empty( $state['expires_at'] ) || $state['expires_at'] <= $now ) {
			// No state yet, or the previous window has already elapsed —
			// start a fresh window from this attempt.
			$state = array(
				'count'      => 0,
				'row_id'     => null,
				'expires_at' => $now + self::get_window(),
			);
		}

		++$state['count'];

		self::persist( $key, $state, $now );

		$max = self::get_max_attempts();

		if ( $state['count'] <= $max ) {
			return array(
				'action' => 'log',
				'count'  => $state['count'],
			);
		}

		if ( $max + 1 === $state['count'] ) {
			return array(
				'action' => 'summarize',
				'count'  => $state['count'],
			);
		}

		if ( empty( $state['row_id'] ) ) {
			// The summary row was never registered (e.g. it was blocked by
			// an exclusion rule, or the event is disabled) — fall back to
			// normal per-attempt logging rather than silently dropping
			// every remaining attempt in the window for no visible reason.
			return array(
				'action' => 'log',
				'count'  => $state['count'],
			);
		}

		return array(
			'action' => 'update',
			'count'  => $state['count'],
			'row_id' => (int) $state['row_id'],
		);
	}

	/**
	 * Register the row ID of the summary row created for a `summarize`
	 * result, so subsequent attempts in the same window know which row to
	 * update instead of inserting a new one.
	 *
	 * @param string $username Attempted username.
	 * @param string $ip       Requesting IP address.
	 * @param int    $row_id   Inserted summary row's ID.
	 * @return void
	 */
	public static function remember_row_id( string $username, string $ip, int $row_id ): void {

		$key   = self::transient_key( $username, $ip );
		$now   = time();
		$state = get_transient( $key );

		if ( ! is_array( $state ) ) {
			return;
		}

		$state['row_id'] = $row_id;

		self::persist( $key, $state, $now );
	}

	/**
	 * Persist throttle state, keeping the transient's expiry pinned to the
	 * window's original end rather than resetting it on every call — a
	 * continuing attack must not indefinitely extend the window.
	 *
	 * @param string $key   Transient key.
	 * @param array  $state State to persist.
	 * @param int    $now   Current timestamp (passed in so callers share one
	 *                      `time()` call rather than drifting between reads).
	 * @return void
	 */
	private static function persist( string $key, array $state, int $now ): void {

		$remaining = max( 1, $state['expires_at'] - $now );

		set_transient( $key, $state, $remaining );
	}

	/**
	 * Build the per-username+IP transient key.
	 *
	 * @param string $username Attempted username.
	 * @param string $ip       Requesting IP address.
	 * @return string
	 */
	private static function transient_key( string $username, string $ip ): string {

		return self::TRANSIENT_PREFIX . md5( strtolower( $username ) . '|' . $ip );
	}
}
