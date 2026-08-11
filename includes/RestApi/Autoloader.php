<?php // phpcs:ignore WordPress.Files.FileName.NotHyphenatedLowercase
namespace LogTrail\RestApi;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use LogTrail\RestApi\Settings\General;
use LogTrail\RestApi\Logs\Logs;
use LogTrail\RestApi\Settings\Events;
use LogTrail\RestApi\Settings\Exclude;
use LogTrail\RestApi\Settings\DataManagement;
use LogTrail\RestApi\Settings\EmailReports;
use LogTrail\RestApi\Dashboard\DashboardController;

/**
 * REST API Loader.
 */
class Autoloader {

	/**
	 * Initialize REST API controllers.
	 *
	 * @return void
	 */
	public static function init() {

		General::init();
		Events::init();
		Logs::init();
		Exclude::init();
		DataManagement::init();
		EmailReports::init();
		DashboardController::init();
	}
}
