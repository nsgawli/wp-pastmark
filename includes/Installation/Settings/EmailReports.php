<?php // phpcs:ignore WordPress.Files.FileName.NotHyphenatedLowercase
namespace Pastmark\Installation\Settings;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Email report settings installer.
 */
class EmailReports {

	/**
	 * Install default settings.
	 *
	 * @return void
	 */
	public static function install() {

		update_option(
			'pastmark_email_reports_settings',
			self::get_default_settings()
		);
	}

	/**
	 * Get default settings.
	 *
	 * @return array
	 */
	public static function get_default_settings() {

		$admin_email = get_option( 'admin_email' );
		$recipients  = is_email( $admin_email ) ? array( $admin_email ) : array();

		return array(
			'enableDailyReport'  => false,
			'dailySendTime'      => '20:00',
			'dailyRecipients'    => $recipients,
			'enableWeeklyReport' => true,
			'weeklySendDay'      => 'friday',
			'weeklySendTime'     => '21:00',
			'weeklyRecipients'   => $recipients,
		);
	}
}
