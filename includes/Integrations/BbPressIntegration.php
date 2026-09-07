<?php // phpcs:ignore WordPress.Files.FileName.NotHyphenatedLowercase

namespace Pastmark\Integrations;

use Pastmark\ActivityLoggers\BbPress\ForumActivityLogger;
use Pastmark\ActivityLoggers\BbPress\ReplyActivityLogger;
use Pastmark\ActivityLoggers\BbPress\SettingsActivityLogger;
use Pastmark\ActivityLoggers\BbPress\TopicActivityLogger;

defined( 'ABSPATH' ) || exit;

/**
 * The bbPress integration manifest (PM-171, Sprint 11).
 *
 * Bundles four loggers behind one cheap presence check, the same shape
 * `ACFIntegration` already established for a multi-logger integration:
 * `ForumActivityLogger`/`TopicActivityLogger`/`ReplyActivityLogger` each
 * cover one of bbPress' three object types (mirroring this codebase's
 * existing one-logger-per-object-type convention), `SettingsActivityLogger`
 * bridges bbPress' own Settings screen the same way `WPSettingsActivityLogger`
 * already handles core settings. Kept deliberately trivial (no constructor,
 * no hook registration) per `docs/BUILDING-AN-INTEGRATION.md`'s guidance -
 * `IntegrationRegistry` calls `is_active()` on this class unconditionally
 * (even when bbPress isn't installed), so any real work belongs in the
 * logger classes below, which are only ever constructed once `is_active()`
 * is confirmed `true`.
 */
class BbPressIntegration implements IntegrationInterface {

	/**
	 * {@inheritDoc}
	 *
	 * `function_exists( 'bbpress' )` is bbPress' own documented
	 * presence-check function (returns the plugin's singleton instance) -
	 * confirmed against the real installed source (`bbpress.php`), the
	 * same cheap check every other integration manifest in this codebase
	 * already uses.
	 */
	public function is_active(): bool {

		return function_exists( 'bbpress' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_logger_classes(): array {

		return array(
			ForumActivityLogger::class,
			TopicActivityLogger::class,
			ReplyActivityLogger::class,
			SettingsActivityLogger::class,
		);
	}
}
