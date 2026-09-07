<?php // phpcs:ignore WordPress.Files.FileName.NotHyphenatedLowercase

namespace Pastmark\Integrations;

use Pastmark\ActivityLoggers\UltimateMember\ProfileActivityLogger;
use Pastmark\ActivityLoggers\UltimateMember\RegistrationActivityLogger;
use Pastmark\ActivityLoggers\UltimateMember\RoleActivityLogger;

defined( 'ABSPATH' ) || exit;

/**
 * The Ultimate Member integration manifest (PM-174, Sprint 12).
 *
 * Bundles three loggers - member registration, profile-field changes, and
 * admin-assigned role changes - behind one cheap presence check, the same
 * shape every prior integration manifest already uses.
 *
 * `RegistrationActivityLogger` is listed first: it's the one that declares
 * the shared `ultimate-member` event group via `pastmark_registered_events`
 * (see its own docblock), matching `BbPressIntegration`'s existing
 * "first logger in the list owns the group" convention.
 *
 * Kept deliberately trivial (no constructor, no hook registration) per
 * `docs/BUILDING-AN-INTEGRATION.md`'s guidance - `IntegrationRegistry`
 * calls `is_active()` on this class unconditionally (even when Ultimate
 * Member isn't installed), so any real work belongs in the logger classes
 * below, which are only ever constructed once `is_active()` is confirmed
 * `true`.
 */
class UltimateMemberIntegration implements IntegrationInterface {

	/**
	 * {@inheritDoc}
	 *
	 * `class_exists( 'UM' )` is Ultimate Member's own singleton class
	 * (`final class UM extends UM_Functions`, `includes/class-init.php`),
	 * confirmed against the real installed source (Ultimate Member 2.13.0,
	 * this dev environment) - the same class the `UM()` accessor function
	 * every UM hook callback relies on returns an instance of. Matches
	 * `WooCommerceIntegration`'s own `class_exists( 'WooCommerce' )`
	 * pattern rather than the heavier `defined( 'um_path' )` alternative -
	 * both are defined unconditionally at plugin load, so either works,
	 * but a class check is this codebase's existing convention.
	 */
	public function is_active(): bool {

		return class_exists( 'UM' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_logger_classes(): array {

		return array(
			RegistrationActivityLogger::class,
			ProfileActivityLogger::class,
			RoleActivityLogger::class,
		);
	}
}
