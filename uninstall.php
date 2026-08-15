<?php // phpcs:ignore WordPress.Files.FileName.NotHyphenatedLowercase
/**
 * Fired when the plugin is uninstalled.
 *
 * @package Pastmark
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

require_once __DIR__ . '/vendor/autoload.php';

use Pastmark\Models\Pastmark_Logs;

/**
 * Handles plugin uninstall cleanup.
 */
class Pastmark_Uninstall {

	/**
	 * Run uninstall cleanup.
	 *
	 * @return void
	 */
	public static function run() {

		if ( ! self::should_remove_data() ) {
			return;
		}

		self::drop_logs_table();
		self::delete_options();
		self::delete_transients();
	}

	/**
	 * Check if settings allow data cleanup.
	 *
	 * @return bool
	 */
	private static function should_remove_data() {

		$data_management_settings = get_option( 'pastmark_data_management_settings', array() );

		return ! empty( $data_management_settings['removeDataOnUninstall'] );
	}

	/**
	 * Drop plugin logs table.
	 *
	 * @return void
	 */
	private static function drop_logs_table() {

		( new Pastmark_Logs() )->drop_table();
	}

	/**
	 * Delete plugin options.
	 *
	 * @return void
	 */
	private static function delete_options() {

		delete_option( 'pastmark_general_settings' );
		delete_option( 'pastmark_exclude_settings' );
		delete_option( 'pastmark_email_reports_settings' );
		delete_option( 'pastmark_event_settings' );
		delete_option( 'pastmark_data_management_settings' );
		delete_option( 'pastmark_registered_events' );
		delete_option( 'pastmark_current_version' );
	}

	/**
	 * Delete plugin transients.
	 *
	 * @return void
	 */
	private static function delete_transients() {

		delete_transient( 'pastmark_installing' );
	}
}

Pastmark_Uninstall::run();
