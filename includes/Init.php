<?php // phpcs:ignore WordPress.Files.FileName.NotHyphenatedLowercase
namespace Pastmark;

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
		if ( PASTMARK_REST_REQUEST ) {
			RestApi\Autoloader::init();
		}

		// Load admin interface.
		if ( PASTMARK_ADMIN_INTERFACE ) {
			Admin\Autoloader::run();
		}

		// Load frontend interface.
		if ( PASTMARK_FRONTEND_INTERFACE ) {
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
