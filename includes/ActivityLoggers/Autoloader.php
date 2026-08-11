<?php // phpcs:ignore WordPress.Files.FileName.NotHyphenatedLowercase
namespace LogTrail\ActivityLoggers;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

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

		if ( class_exists( 'WooCommerce' ) ) {

			new WooCommerce\ProductActivityLogger();

			new WooCommerce\ProductCategoryActivityLogger();

			new WooCommerce\OrderActivityLogger();

			new WooCommerce\CouponActivityLogger();

			new WooCommerce\ReviewActivityLogger();
		}
	}
}
