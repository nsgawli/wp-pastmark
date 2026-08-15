<?php // phpcs:ignore WordPress.Files.FileName.NotHyphenatedLowercase
namespace Pastmark\RestApi;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Pastmark\RestApi\Settings\General;
use Pastmark\RestApi\Logs\Logs;
use Pastmark\RestApi\Settings\Events;
use Pastmark\RestApi\Settings\Exclude;
use Pastmark\RestApi\Settings\DataManagement;
use Pastmark\RestApi\Settings\EmailReports;
use Pastmark\RestApi\Dashboard\DashboardController;

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
