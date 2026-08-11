<?php // phpcs:ignore WordPress.Files.FileName.NotHyphenatedLowercase
namespace LogTrail\Admin;

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

		// load admin style and scripts.
		Admin::init();

		// activity logs page.
		ActivityLogsPage::init();

		// load setting admin page.
		SettingsPage::init();

		// load event settings page.
		EventsPage::init();

		// load dashboard page.
		DashboardPage::init();

		// load WordPress dashboard widget.
		DashboardWidget::init();
	}
}
