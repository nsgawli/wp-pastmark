<?php // phpcs:ignore WordPress.Files.FileName.NotHyphenatedLowercase

namespace Pastmark\Integrations;

use Pastmark\ActivityLoggers\Redirection\GroupActivityLogger;
use Pastmark\ActivityLoggers\Redirection\RedirectActivityLogger;

defined( 'ABSPATH' ) || exit;

/**
 * Redirection integration manifest (PM-168, Sprint 10).
 *
 * Bundles two loggers behind one cheap presence check, the same shape
 * every other integration already establishes. Unlike the SEO integrations
 * (Yoast/RankMath), Redirection doesn't use a custom post type or WordPress
 * options for its rule data - it stores redirect rules and groups in its
 * own dedicated DB tables (`{$wpdb->prefix}redirection_items`/
 * `_groups`, confirmed via `database/schema/latest.php`), the same shape
 * Gravity Forms already established (Sprint 8) for this codebase.
 * `RedirectActivityLogger` is listed first so it's the one that registers
 * the shared `redirection` event group - see its own `register_events()`
 * docblock.
 */
class RedirectionIntegration implements IntegrationInterface {

	/**
	 * {@inheritDoc}
	 *
	 * `Red_Item` is Redirection's own redirect-rule model class, confirmed
	 * `require_once`'d unconditionally near the very top of `redirection.php`
	 * (before any admin/REST/front/CLI branching), so it's defined in every
	 * request context by the time `plugins_loaded` fires - reliable at the
	 * timing `IntegrationRegistry::load_all()` runs. Confirmed against the
	 * real installed plugin (Redirection 5.9.0) in this environment.
	 */
	public function is_active(): bool {

		return class_exists( 'Red_Item' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_logger_classes(): array {

		return array(
			RedirectActivityLogger::class,
			GroupActivityLogger::class,
		);
	}
}
