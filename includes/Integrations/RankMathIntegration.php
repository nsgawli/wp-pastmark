<?php // phpcs:ignore WordPress.Files.FileName.NotHyphenatedLowercase

namespace Pastmark\Integrations;

use Pastmark\ActivityLoggers\RankMath\PostMetaActivityLogger;
use Pastmark\ActivityLoggers\RankMath\SettingsActivityLogger;

defined( 'ABSPATH' ) || exit;

/**
 * RankMath integration manifest (PM-167, Sprint 10).
 *
 * Bundles two loggers behind one cheap presence check, the same shape
 * `YoastSeoIntegration` already establishes: `SettingsActivityLogger`
 * covers RankMath's four real global-settings options (confirmed against
 * `includes/class-settings.php`) plus its native module-toggle action,
 * `PostMetaActivityLogger` covers per-post SEO meta box saves
 * (`rank_math_*` post meta, confirmed against `includes/class-metadata.php`
 * and `includes/admin/metabox/class-screen.php`). `SettingsActivityLogger`
 * is listed first so it's the one that registers the shared `rank-math`
 * event group - see its own `register_events()` docblock. Kept
 * deliberately trivial (no constructor, no hook registration) per
 * `docs/BUILDING-AN-INTEGRATION.md`'s guidance.
 */
class RankMathIntegration implements IntegrationInterface {

	/**
	 * {@inheritDoc}
	 *
	 * `RankMath` is the plugin's own main singleton class, confirmed
	 * defined unconditionally in `rank-math.php` for both the free and Pro
	 * builds - matches WP Activity Log's own real detection code
	 * (`Rank_Math_Helper::is_rank_math_active()`), confirmed against the
	 * real installed plugin (RankMath 1.0.277.1, free) in this environment.
	 */
	public function is_active(): bool {

		return class_exists( 'RankMath' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_logger_classes(): array {

		return array(
			SettingsActivityLogger::class,
			PostMetaActivityLogger::class,
		);
	}
}
