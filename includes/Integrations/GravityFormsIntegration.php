<?php // phpcs:ignore WordPress.Files.FileName.NotHyphenatedLowercase

namespace Pastmark\Integrations;

use Pastmark\ActivityLoggers\GravityForms\ConfirmationActivityLogger;
use Pastmark\ActivityLoggers\GravityForms\EntryActivityLogger;
use Pastmark\ActivityLoggers\GravityForms\FormActivityLogger;
use Pastmark\ActivityLoggers\GravityForms\FormSettingsActivityLogger;
use Pastmark\ActivityLoggers\GravityForms\NotificationActivityLogger;

defined( 'ABSPATH' ) || exit;

/**
 * Gravity Forms integration manifest (PM-156, Sprint 8's third and final
 * first-wave forms integration; grown to five loggers by PM-169's granular
 * parity pass - see `Actions::FORM_ACTIVATE`'s docblock).
 *
 * Bundles five loggers behind one cheap presence check, the same shape
 * `WooCommerceIntegration`/`ACFIntegration`/`WPFormsIntegration` already
 * established: `FormActivityLogger` covers form create/update/delete/trash/
 * restore/duplicate/activate/deactivate, `ConfirmationActivityLogger` and
 * `NotificationActivityLogger` cover those two form sub-resources' own
 * create/update/delete/activate/deactivate lifecycle, `FormSettingsActivityLogger`
 * covers form import/export and Gravity Forms' own global plugin settings,
 * and `EntryActivityLogger` covers real visitor submissions, entry
 * moderation (star/read/trash/delete/notes/admin edits/export), plus - the
 * one plugin of the three where this is a real, reliable feature rather
 * than best-effort - notification-*send*-failure detection (see that
 * class's docblock: `gform_after_email` hands over the actual send result
 * directly, unlike WPForms/ACF). Kept deliberately trivial (no constructor,
 * no hook registration) per `docs/BUILDING-AN-INTEGRATION.md` - real work
 * belongs in the logger classes below, only ever constructed once
 * `is_active()` is confirmed `true`.
 *
 * Gravity Forms uses its own dedicated DB tables, not a custom post type -
 * `class_exists( 'GFForms' )` is the only reliable presence check (no
 * `post_type` shortcut exists, unlike ACF/WPForms).
 */
class GravityFormsIntegration implements IntegrationInterface {

	/**
	 * {@inheritDoc}
	 */
	public function is_active(): bool {

		return class_exists( 'GFForms' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_logger_classes(): array {

		return array(
			FormActivityLogger::class,
			EntryActivityLogger::class,
			ConfirmationActivityLogger::class,
			NotificationActivityLogger::class,
			FormSettingsActivityLogger::class,
		);
	}
}
