<?php // phpcs:ignore WordPress.Files.FileName.NotHyphenatedLowercase

namespace Pastmark\Integrations;

use Pastmark\ActivityLoggers\WPForms\EntryActivityLogger;
use Pastmark\ActivityLoggers\WPForms\FormActivityLogger;
use Pastmark\ActivityLoggers\WPForms\FormSettingsActivityLogger;

defined( 'ABSPATH' ) || exit;

/**
 * WPForms integration manifest (PM-155, Sprint 8; grown to three loggers by
 * PM-170's granular parity pass - see `FormActivityLogger`'s PM-170
 * addendum docblock).
 *
 * Bundles three loggers behind one cheap presence check, the same shape
 * `WooCommerceIntegration`/`ACFIntegration` already established:
 * `FormActivityLogger` covers form create/update/rename/delete/trash/
 * restore/duplicate plus its confirmation/notification sub-resource events,
 * `FormSettingsActivityLogger` covers WPForms' own global settings, service/
 * provider integrations, and addon lifecycle, and `EntryActivityLogger`
 * covers real visitor submissions plus best-effort notification-failure
 * detection (see that class's docblock - WPForms itself exposes no
 * send-result signal, per this sprint's own pre-ticketing research). Kept
 * deliberately trivial (no constructor, no hook registration) per
 * `docs/BUILDING-AN-INTEGRATION.md` - real work belongs in the logger
 * classes below, only ever constructed once `is_active()` is confirmed
 * `true`.
 */
class WPFormsIntegration implements IntegrationInterface {

	/**
	 * {@inheritDoc}
	 */
	public function is_active(): bool {

		return function_exists( 'wpforms' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_logger_classes(): array {

		return array(
			FormActivityLogger::class,
			EntryActivityLogger::class,
			FormSettingsActivityLogger::class,
		);
	}
}
