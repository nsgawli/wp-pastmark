<?php // phpcs:ignore WordPress.Files.FileName.NotHyphenatedLowercase

namespace Pastmark\Integrations;

use Pastmark\ActivityLoggers\ACF\FieldActivityLogger;
use Pastmark\ActivityLoggers\ACF\FieldGroupActivityLogger;

defined( 'ABSPATH' ) || exit;

/**
 * Advanced Custom Fields integration manifest (PM-154, Sprint 8's first
 * new-integration ticket).
 *
 * Bundles two loggers behind one cheap presence check, the same shape
 * `WooCommerceIntegration` already established: `FieldGroupActivityLogger`
 * covers field-group create/update/delete/trash/restore/duplicate,
 * `FieldActivityLogger` covers the per-field create/update/delete events ACF
 * already fires with finer granularity than the group-level hooks alone.
 * Kept deliberately trivial (no constructor, no hook registration) per
 * `docs/BUILDING-AN-INTEGRATION.md`'s guidance - `IntegrationRegistry` calls
 * `is_active()` on this class unconditionally (even when ACF isn't
 * installed), so any real work belongs in the logger classes below, which
 * are only ever constructed once `is_active()` is confirmed `true`.
 */
class ACFIntegration implements IntegrationInterface {

	/**
	 * {@inheritDoc}
	 */
	public function is_active(): bool {

		return class_exists( 'ACF' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_logger_classes(): array {

		return array(
			FieldGroupActivityLogger::class,
			FieldActivityLogger::class,
		);
	}
}
