<?php // phpcs:ignore WordPress.Files.FileName.NotHyphenatedLowercase
namespace Pastmark\ActivityLoggers;

use Pastmark\Integrations\IntegrationRegistry;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Instantiates every core `AbstractLogger` subclass, then hands
 * third-party-plugin integration loading off to `IntegrationRegistry`
 * (PM-149/PM-150).
 */
class Autoloader {

	/**
	 * Initialize the autoloader.
	 *
	 * @version 1.0.0
	 * @return void
	 */
	public static function run() {

		new UserActivityLogger();

		new PostActivityLogger();

		new CommentActivityLogger();

		new MediaActivityLogger();

		new PluginActivityLogger();

		new ThemeActivityLogger();

		new WPSettingsActivityLogger();

		new MenuActivityLogger();

		new WidgetActivityLogger();

		// Third-party-plugin integrations (WooCommerce today; more join
		// via IntegrationRegistry::register() from Sprint 8 onward) load
		// through the generic registry (PM-149/PM-150) instead of a
		// hardcoded `if ( class_exists( 'WooCommerce' ) )` block here.
		IntegrationRegistry::load_all();
	}
}
