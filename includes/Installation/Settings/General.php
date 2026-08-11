<?php // phpcs:ignore WordPress.Files.FileName.NotHyphenatedLowercase
namespace LogTrail\Installation\Settings;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class General {

	/**
	 * Initialize the class.
	 *
	 * @version 1.0.0
	 * @return void
	 */
	public static function install() {

		update_option( 'logtrail_general_settings', self::get_default_settings() );
	}

	/**
	 * Get default settings
	 *
	 * @version 1.0.0
	 * @return array
	 */
	public static function get_default_settings() {

		return array(
			'dashboardWidget'      => true,
			'eventTimestamp'       => 'utc',
			'logDetailsViewMode'   => 'drawer',
			'logsPageViewMode'     => 'timeline',
			'enableAutoDeleteLogs' => true,
			'autoDeleteTime'       => 3,
			'autoDeleteUnit'       => 'month',
		);
	}
}
