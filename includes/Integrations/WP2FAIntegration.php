<?php // phpcs:ignore WordPress.Files.FileName.NotHyphenatedLowercase

namespace Pastmark\Integrations;

use Pastmark\ActivityLoggers\WP2FA\TwoFactorActivityLogger;

defined( 'ABSPATH' ) || exit;

/**
 * The WP 2FA integration manifest (PM-172, Sprint 11).
 *
 * Bundles the single `TwoFactorActivityLogger` behind one cheap presence
 * check, the same shape every prior integration manifest already uses.
 * Kept deliberately trivial (no constructor, no hook registration) per
 * `docs/BUILDING-AN-INTEGRATION.md`'s guidance - `IntegrationRegistry`
 * calls `is_active()` on this class unconditionally (even when WP 2FA
 * isn't installed), so any real work belongs in the logger class below,
 * which is only ever constructed once `is_active()` is confirmed `true`.
 */
class WP2FAIntegration implements IntegrationInterface {

	/**
	 * {@inheritDoc}
	 *
	 * `defined( 'WP_2FA_VERSION' )` is WP 2FA's own presence marker,
	 * confirmed against the real installed source (`wp-2fa.php`, WP 2FA
	 * 4.1.0, this dev environment) - the same check WP Activity Log's own
	 * WP 2FA sensor uses.
	 */
	public function is_active(): bool {

		return defined( 'WP_2FA_VERSION' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_logger_classes(): array {

		return array(
			TwoFactorActivityLogger::class,
		);
	}
}
