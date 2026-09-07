<?php // phpcs:ignore WordPress.Files.FileName.NotHyphenatedLowercase

namespace Pastmark\Integrations;

use Pastmark\ActivityLoggers\YoastSeo\PostMetaActivityLogger;
use Pastmark\ActivityLoggers\YoastSeo\SettingsActivityLogger;

defined( 'ABSPATH' ) || exit;

/**
 * Yoast SEO integration manifest (PM-166, Sprint 10).
 *
 * Bundles two loggers behind one cheap presence check, the same shape
 * `ACFIntegration`/`WPFormsIntegration` already establish:
 * `SettingsActivityLogger` covers Yoast's three global-settings options
 * (`wpseo`, `wpseo_titles`, `wpseo_social` - confirmed against the real
 * installed plugin's own `inc/options/class-wpseo-option-*.php` classes,
 * not assumed from documentation), `PostMetaActivityLogger` covers per-post
 * SEO meta box saves (`_yoast_wpseo_*` post meta, confirmed against
 * `WPSEO_Meta::$meta_prefix`). `SettingsActivityLogger` is listed first so
 * it's the one that registers the shared `yoast-seo` event group covering
 * both loggers' actions - see its own `register_events()` docblock. Kept
 * deliberately trivial (no constructor, no hook registration) per
 * `docs/BUILDING-AN-INTEGRATION.md`'s guidance - `IntegrationRegistry` calls
 * `is_active()` on this class unconditionally (even when Yoast isn't
 * installed), so any real work belongs in the logger classes below, which
 * are only ever constructed once `is_active()` is confirmed `true`.
 */
class YoastSeoIntegration implements IntegrationInterface {

	/**
	 * {@inheritDoc}
	 *
	 * `WPSEO_VERSION` is Yoast's own presence constant
	 * (`wp-seo-main.php`), defined for both the free and premium builds -
	 * confirmed against the real installed plugin (Yoast SEO 28.3) in this
	 * environment, not assumed from Yoast's own documentation.
	 */
	public function is_active(): bool {

		return defined( 'WPSEO_VERSION' );
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
