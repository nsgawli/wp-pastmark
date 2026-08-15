<?php // phpcs:ignore WordPress.Files.FileName.NotHyphenatedLowercase
namespace Pastmark\Services;

use Pastmark\Models\Pastmark_Logs;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Auto-delete logs service powered by WordPress cron.
 */
class AutoDeleteLogsService {

	/**
	 * Cron hook name.
	 */
	private const CRON_HOOK = 'pastmark_auto_delete_logs_cron';

	/**
	 * Initialize hooks.
	 *
	 * @return void
	 */
	public static function init() {

		add_action( 'init', array( __CLASS__, 'maybe_schedule_event' ) );

		add_action( self::CRON_HOOK, array( __CLASS__, 'run_cleanup' ) );

		add_action(
			'update_option_pastmark_general_settings',
			array( __CLASS__, 'on_settings_updated' ),
			10,
			2
		);
	}

	/**
	 * Handle settings updates and refresh scheduling.
	 *
	 * @param mixed $old_value Old option value.
	 * @param mixed $new_value New option value.
	 * @return void
	 */
	public static function on_settings_updated( $old_value, $new_value ) {

		self::maybe_schedule_event();
	}

	/**
	 * Schedule or unschedule cron based on settings.
	 *
	 * @return void
	 */
	public static function maybe_schedule_event() {

		if ( self::is_enabled() ) {

			if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
				wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::CRON_HOOK );
			}

			return;
		}

		self::clear_scheduled_event();
	}

	/**
	 * Run cleanup and delete old logs.
	 *
	 * @return void
	 */
	public static function run_cleanup() {

		if ( ! self::is_enabled() ) {
			self::clear_scheduled_event();
			return;
		}

		$cutoff = self::get_cutoff_datetime_utc();

		if ( '' === $cutoff ) {
			return;
		}

		$model = new Pastmark_Logs();
		$model->delete_logs_older_than( $cutoff );
	}

	/**
	 * Check whether auto-delete is enabled.
	 *
	 * @return bool
	 */
	private static function is_enabled(): bool {

		$settings = get_option( 'pastmark_general_settings', array() );

		if ( ! is_array( $settings ) ) {
			return true;
		}

		if ( ! array_key_exists( 'enableAutoDeleteLogs', $settings ) ) {
			return true;
		}

		return (bool) $settings['enableAutoDeleteLogs'];
	}

	/**
	 * Build UTC datetime cutoff according to retention settings.
	 *
	 * @return string
	 */
	private static function get_cutoff_datetime_utc(): string {

		$settings = get_option( 'pastmark_general_settings', array() );

		$amount = 3;
		if ( is_array( $settings ) && isset( $settings['autoDeleteTime'] ) ) {
			$amount = max( 1, (int) $settings['autoDeleteTime'] );
		}

		$unit = 'month';
		if ( is_array( $settings ) && isset( $settings['autoDeleteUnit'] ) ) {
			$candidate = sanitize_text_field( (string) $settings['autoDeleteUnit'] );
			if ( in_array( $candidate, array( 'day', 'month', 'year' ), true ) ) {
				$unit = $candidate;
			}
		}

		try {
			$now = new \DateTimeImmutable( 'now', new \DateTimeZone( 'UTC' ) );
			$cutoff = $now->modify( sprintf( '-%d %s', $amount, $unit ) );
		} catch ( \Exception $exception ) {
			return '';
		}

		if ( false === $cutoff ) {
			return '';
		}

		return $cutoff->format( 'Y-m-d H:i:s' );
	}

	/**
	 * Unschedule all pending cleanup events.
	 *
	 * @return void
	 */
	private static function clear_scheduled_event() {

		$timestamp = wp_next_scheduled( self::CRON_HOOK );

		while ( $timestamp ) {
			wp_unschedule_event( $timestamp, self::CRON_HOOK );
			$timestamp = wp_next_scheduled( self::CRON_HOOK );
		}
	}
}
