<?php // phpcs:ignore WordPress.Files.FileName.NotHyphenatedLowercase

namespace Pastmark\Integrations;

use Pastmark\ActivityLoggers\TutorLms\CourseContentActivityLogger;
use Pastmark\ActivityLoggers\TutorLms\ProgressActivityLogger;

defined( 'ABSPATH' ) || exit;

/**
 * The Tutor LMS integration manifest (PM-176, Sprint 12).
 *
 * Bundles two loggers - course/lesson/topic/quiz content changes, and
 * student enrollment/completion progress - behind one cheap presence
 * check, the same shape every prior integration manifest already uses.
 *
 * `CourseContentActivityLogger` is listed first: it's the one that
 * declares the shared `tutor-lms` event group via
 * `pastmark_registered_events` (see its own docblock), matching
 * `BbPressIntegration`'s existing "first logger in the list owns the
 * group" convention.
 *
 * Kept deliberately trivial (no constructor, no hook registration) per
 * `docs/BUILDING-AN-INTEGRATION.md`'s guidance - `IntegrationRegistry`
 * calls `is_active()` on this class unconditionally (even when Tutor LMS
 * isn't installed), so any real work belongs in the logger classes below,
 * which are only ever constructed once `is_active()` is confirmed `true`.
 */
class TutorLmsIntegration implements IntegrationInterface {

	/**
	 * {@inheritDoc}
	 *
	 * `function_exists( 'tutor' )` is Tutor LMS's own config-accessor
	 * function (`includes/tutor-general-functions.php`, returns a
	 * `\TUTOR\Config` instance), confirmed against the real installed
	 * source (Tutor LMS 4.0.7, this dev environment) - not
	 * `function_exists( 'tutor_lms' )`/`class_exists( 'TUTOR\Tutor' )`,
	 * a second, unrelated accessor/main-bootstrap-class pair the same
	 * plugin also defines. Matches `WPFormsIntegration`'s own
	 * `function_exists( 'wpforms' )` pattern - a plugin's own accessor
	 * function, not a heavier `is_plugin_active()` check.
	 */
	public function is_active(): bool {

		return function_exists( 'tutor' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_logger_classes(): array {

		return array(
			CourseContentActivityLogger::class,
			ProgressActivityLogger::class,
		);
	}
}
