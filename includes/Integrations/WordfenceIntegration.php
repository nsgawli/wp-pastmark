<?php // phpcs:ignore WordPress.Files.FileName.NotHyphenatedLowercase

namespace Pastmark\Integrations;

use Pastmark\ActivityLoggers\Wordfence\FirewallActivityLogger;
use Pastmark\ActivityLoggers\Wordfence\LoginSecurityActivityLogger;
use Pastmark\ActivityLoggers\Wordfence\SettingsActivityLogger;

defined( 'ABSPATH' ) || exit;

/**
 * The Wordfence integration manifest (PM-175, Sprint 12).
 *
 * Bundles three loggers - login-security events, firewall/IP-blocking
 * events, and firewall/scan configuration changes - behind one cheap
 * presence check, the same shape every prior integration manifest already
 * uses.
 *
 * **Malware-scan-completion logging is deliberately out of scope, with the
 * gap disclosed rather than guessed at** (per this ticket's own AC
 * fallback) - see `docs/sprints/sprint-12.md`'s PM-175 entry and
 * `LoginSecurityActivityLogger`'s own docblock for the full finding:
 * Wordfence stores its own state (including `lastScanCompleted`) in a
 * custom `wp_wfConfig` database table, bypassing WordPress' Options API
 * entirely, and the one dispatcher that fires per-key actions from that
 * table (`wfConfig::_handleActionHooks()`) has no case for that key -
 * confirmed by reading the real installed source (Wordfence 9.0.0, this
 * dev environment), not assumed from the plugin's marketing copy.
 *
 * `LoginSecurityActivityLogger` is listed first: it's the one that
 * declares the shared `wordfence` event group via
 * `pastmark_registered_events` (see its own docblock), matching
 * `BbPressIntegration`'s existing "first logger in the list owns the
 * group" convention.
 *
 * Kept deliberately trivial (no constructor, no hook registration) per
 * `docs/BUILDING-AN-INTEGRATION.md`'s guidance - `IntegrationRegistry`
 * calls `is_active()` on this class unconditionally (even when Wordfence
 * isn't installed), so any real work belongs in the logger classes below,
 * which are only ever constructed once `is_active()` is confirmed `true`.
 */
class WordfenceIntegration implements IntegrationInterface {

	/**
	 * {@inheritDoc}
	 *
	 * `class_exists( 'wordfence' )` is Wordfence's own main class
	 * (`lib/wordfenceClass.php`), confirmed against the real installed
	 * source (Wordfence 9.0.0, this dev environment). Matches
	 * `WooCommerceIntegration`'s own `class_exists( 'WooCommerce' )`
	 * pattern rather than the heavier `defined( 'WORDFENCE_VERSION' )`
	 * alternative - both are defined unconditionally at plugin load, so
	 * either works, but a class check is this codebase's existing
	 * convention.
	 */
	public function is_active(): bool {

		return class_exists( 'wordfence' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_logger_classes(): array {

		return array(
			LoginSecurityActivityLogger::class,
			FirewallActivityLogger::class,
			SettingsActivityLogger::class,
		);
	}
}
