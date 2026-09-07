<?php // phpcs:ignore WordPress.Files.FileName.NotHyphenatedLowercase
namespace Pastmark\Installation\Settings;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Security & Privacy settings installer.
 *
 * Backs `anonymizeIp` (PM-137) and the failed-login throttle tuning
 * (`loginThrottleWindow`/`loginThrottleMax`, consumed by `Utils\LoginThrottle`
 * via the `pastmark_login_throttle_window`/`pastmark_login_throttle_max_attempts`
 * filters — see `RestApi\Settings\Security`, which wires the stored option to
 * those filters).
 */
class Security {

	/**
	 * Install default settings.
	 *
	 * @return void
	 */
	public static function install() {

		update_option( 'pastmark_security_settings', self::get_default_settings() );
	}

	/**
	 * Get default settings.
	 *
	 * `anonymizeIp` defaults to `false`: anonymizing forfeits detail a site
	 * owner may genuinely need for a real security investigation, so this
	 * is an opt-in privacy choice rather than an on-by-default one — unlike
	 * masking (PM-135) and throttling (PM-136), which are safe to apply
	 * unconditionally because they don't remove information anyone would
	 * reasonably want to look up later.
	 *
	 * @return array
	 */
	public static function get_default_settings() {

		return array(
			'anonymizeIp'         => false,
			'loginThrottleWindow' => 300,
			'loginThrottleMax'    => 5,
		);
	}
}
