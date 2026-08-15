<?php // phpcs:ignore WordPress.Files.FileName.NotHyphenatedLowercase

namespace Pastmark\Utils;

defined( 'ABSPATH' ) || exit;

/**
 * Generic helper methods.
 */
class Helpers {

	/**
	 * Humanize underscored label.
	 *
	 * @param string $value Value.
	 *
	 * @return string
	 */
	public static function humanize_label(
		string $value
	): string {

		return ucwords(
			str_replace(
				'_',
				' ',
				$value
			)
		);
	}

	/**
	 * Format a MySQL UTC timestamp for display.
	 *
	 * @param string $timestamp MySQL datetime in UTC.
	 * @return string
	 */
	public static function format_timestamp_for_display( string $timestamp ): string {

		if ( '' === $timestamp ) {
			return '';
		}

		$format = get_option( 'date_format' ) . ' ' . get_option( 'time_format' );

		try {
			$utc_date = new \DateTimeImmutable( $timestamp, new \DateTimeZone( 'UTC' ) );
		} catch ( \Exception $exception ) {
			return $timestamp;
		}

		$timezone = self::is_local_timestamp_mode()
			? wp_timezone()
			: new \DateTimeZone( 'UTC' );

		return wp_date( $format, $utc_date->getTimestamp(), $timezone );
	}

	/**
	 * Whether timestamps should be shown in WordPress local timezone.
	 *
	 * @return bool
	 */
	private static function is_local_timestamp_mode(): bool {

		$settings = get_option( 'pastmark_general_settings', array() );

		if ( ! is_array( $settings ) ) {
			return false;
		}

		if ( ! isset( $settings['eventTimestamp'] ) ) {
			return false;
		}

		return 'local' === $settings['eventTimestamp'];
	}

	/**
	 * Get plugin data.
	 *
	 * @param string $plugin Plugin path.
	 * @return array
	 */
	public static function get_plugin_data( string $plugin ): array {

		if ( ! function_exists( 'get_plugin_data' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$plugin_file = WP_PLUGIN_DIR . '/' . $plugin;

		if ( ! file_exists( $plugin_file ) ) {
			return array();
		}

		$data = get_plugin_data( $plugin_file );

		return array(
			'name'    => $data['Name'] ?? '',
			'version' => $data['Version'] ?? '',
			'author'  => $data['Author'] ?? '',
			'plugin'  => $plugin,
		);
	}
}
