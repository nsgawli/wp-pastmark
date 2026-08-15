<?php // phpcs:ignore WordPress.Files.FileName.NotHyphenatedLowercase
namespace Pastmark\Installation\Settings;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Data Management settings installer.
 */
class DataManagement {

	/**
	 * Install default settings.
	 *
	 * @return void
	 */
	public static function install() {

		update_option( 'pastmark_data_management_settings', self::get_default_settings() );
	}

	/**
	 * Get default settings.
	 *
	 * @return array
	 */
	public static function get_default_settings() {

		return array(
			'removeDataOnUninstall' => false,
		);
	}
}
