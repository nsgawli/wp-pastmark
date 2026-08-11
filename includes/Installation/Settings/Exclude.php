<?php // phpcs:ignore WordPress.Files.FileName.NotHyphenatedLowercase

namespace LogTrail\Installation\Settings;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Exclude settings installer.
 */
class Exclude {

	/**
	 * Install default settings.
	 *
	 * @return void
	 */
	public static function install() {

		update_option(
			'logtrail_exclude_settings',
			self::get_default_settings()
		);
	}

	/**
	 * Get default settings.
	 *
	 * @return array
	 */
	/**
	 * Get default settings.
	 *
	 * @return array
	 */
	public static function get_default_settings() {

		return array(
			'excludedUsers'       => array(),
			'excludedRoles'       => array(),
			'excludedIPs'         => array(),
			'excludedPostTypes'   => array(),
			'excludedStatuses'    => array(),
			'excludedPostMeta'    => array(),
			'excludedUserMeta'    => array(),
			'excludedPlugins'     => array(),
			'excludedThemes'      => array(),
			'excludedWidgets'     => array(),
			'excludedMenus'       => array(),
			'excludeCronRequests' => false,
		);
	}
}
