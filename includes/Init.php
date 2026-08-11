<?php // phpcs:ignore WordPress.Files.FileName.NotHyphenatedLowercase
namespace LogTrail;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Init {

	/**
	 * Initialize the autoloader.
	 *
	 * @version 1.0.0
	 * @return void
	 */
	public static function run() {

		// Load installation functionality.
		Installation\Autoloader::run();

		// Load ajax functionality.
		if ( wp_doing_ajax() ) {
			Ajax\Autoloader::run();
		}

		// Load rest api functionality.
		if ( WPLT_REST_REQUEST ) {
			RestApi\Autoloader::init();
		}

		// Load admin interface.
		if ( WPLT_ADMIN_INTERFACE ) {
			Admin\Autoloader::run();
		}

		// Load frontend interface.
		if ( WPLT_FRONTEND_INTERFACE ) {
			Frontend\Autoloader::run();
		}

		// Load activity loggers.
		ActivityLoggers\Autoloader::run();

		// Load WordPress GDPR (export/erase personal data) integration.
		Privacy\Autoloader::run();

		// Load scheduled maintenance services.
		Services\AutoDeleteLogsService::init();
		Services\EmailReportsService::init();
	}
}
