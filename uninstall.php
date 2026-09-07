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

use Pastmark\Dashboard\DashboardLastViewed;
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
		self::delete_user_meta();
		self::delete_transients();
		self::clear_scheduled_events();
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
		delete_option( 'pastmark_event_log_level' );
		delete_option( 'pastmark_data_management_settings' );
		delete_option( 'pastmark_security_settings' );
		delete_option( 'pastmark_registered_events' );
		delete_option( 'pastmark_current_version' );
	}

	/**
	 * Delete plugin-owned per-user meta.
	 *
	 * No existing routine in this codebase cleans up per-user meta on
	 * uninstall (confirmed while adding PM-144's dashboard "last viewed"
	 * tracking, its first user) - `delete_metadata()` with `$delete_all`
	 * true removes the key across every user in one call, the same way
	 * `delete_option()` above removes a site-wide option.
	 *
	 * @return void
	 */
	private static function delete_user_meta() {

		delete_metadata( 'user', 0, DashboardLastViewed::META_KEY, '', true );
	}

	/**
	 * Delete plugin transients.
	 *
	 * @return void
	 */
	private static function delete_transients() {

		delete_transient( 'pastmark_installing' );
	}

	/**
	 * Defensively re-clear the plugin's cron hooks.
	 *
	 * `register_deactivation_hook()` (see `Installation\Autoloader::deactivate()`)
	 * already clears these on a normal deactivate-then-delete flow, but
	 * covers the case where a site's files are removed without a clean
	 * deactivation pass first. Known limitation: WP-CLI's `plugin delete`
	 * on an already-inactive plugin still runs `uninstall.php` (so this
	 * still applies), but if the *files* are removed by other means
	 * (e.g. direct FTP/filesystem deletion) neither hook ever fires and
	 * the crons are left scheduled - not worth over-building around here.
	 *
	 * @return void
	 */
	private static function clear_scheduled_events() {

		wp_clear_scheduled_hook( 'pastmark_auto_delete_logs_cron' );
		wp_clear_scheduled_hook( 'pastmark_send_daily_report_cron' );
		wp_clear_scheduled_hook( 'pastmark_send_weekly_report_cron' );
	}
}

Pastmark_Uninstall::run();
