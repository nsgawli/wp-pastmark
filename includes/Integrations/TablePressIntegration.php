<?php // phpcs:ignore WordPress.Files.FileName.NotHyphenatedLowercase

namespace Pastmark\Integrations;

use Pastmark\ActivityLoggers\TablePress\TableActivityLogger;

defined( 'ABSPATH' ) || exit;

/**
 * TablePress integration manifest (PM-170, Sprint 11).
 *
 * Bundles the single `TableActivityLogger` behind one cheap presence check,
 * the same shape every prior integration manifest already uses. Kept
 * deliberately trivial (no constructor, no hook registration) per
 * `docs/BUILDING-AN-INTEGRATION.md`'s guidance - `IntegrationRegistry` calls
 * `is_active()` on this class unconditionally (even when TablePress isn't
 * installed), so any real work belongs in the logger class below, which is
 * only ever constructed once `is_active()` is confirmed `true`.
 */
class TablePressIntegration implements IntegrationInterface {

	/**
	 * {@inheritDoc}
	 */
	public function is_active(): bool {

		return class_exists( 'TablePress' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_logger_classes(): array {

		return array(
			TableActivityLogger::class,
		);
	}
}
