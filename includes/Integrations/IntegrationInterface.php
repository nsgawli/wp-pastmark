<?php // phpcs:ignore WordPress.Files.FileName.NotHyphenatedLowercase

namespace Pastmark\Integrations;

defined( 'ABSPATH' ) || exit;

/**
 * Contract every third-party-plugin integration manifest implements.
 *
 * An integration typically bundles several `AbstractLogger` subclasses
 * (WooCommerce ships five) behind one cheap presence check - it is not
 * itself an `AbstractLogger` subclass. Registered through
 * `IntegrationRegistry::register()` or the built-in list, then loaded by
 * `IntegrationRegistry::load_all()`. See
 * `docs/INTEGRATION-FRAMEWORK-ARCHITECTURE.md` for the design this
 * implements (Sprint 7, PM-149).
 */
interface IntegrationInterface {

	/**
	 * Cheap presence check deciding whether this integration's loggers
	 * get instantiated at all - a `function_exists()`/`class_exists()`
	 * check, matching WooCommerce's existing pattern. Deliberately not
	 * the heavier `is_plugin_active()` tier (see the architecture doc's
	 * decision 2 for why that's not built yet).
	 *
	 * @return bool
	 */
	public function is_active(): bool;

	/**
	 * `AbstractLogger` subclass names this integration loads when
	 * `is_active()` returns true.
	 *
	 * @return string[]
	 */
	public function get_logger_classes(): array;
}
