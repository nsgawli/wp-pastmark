<?php // phpcs:ignore WordPress.Files.FileName.NotHyphenatedLowercase
namespace Pastmark\Privacy;

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

		// register with WordPress' "Export Personal Data" tool.
		PersonalDataExporter::init();

		// register with WordPress' "Erase Personal Data" tool.
		PersonalDataEraser::init();
	}
}
