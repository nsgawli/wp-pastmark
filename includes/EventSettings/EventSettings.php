<?php // phpcs:ignore WordPress.Files.FileName.NotHyphenatedLowercase

namespace LogTrail\EventSettings;

use LogTrail\Installation\Settings\Events as InstallationEvents;

defined( 'ABSPATH' ) || exit;

/**
 * Event settings manager.
 */
class EventSettings {

	/**
	 * Option name.
	 *
	 * @var string
	 */
	const OPTION_NAME = 'logtrail_event_settings';

	/**
	 * Log level option name.
	 *
	 * @var string
	 */
	const LOG_LEVEL_OPTION_NAME = 'logtrail_event_log_level';

	/**
	 * Allowed log levels.
	 *
	 * @var string[]
	 */
	const ALLOWED_LOG_LEVELS = array(
		'essential',
		'recommended',
		'complete',
		'custom',
	);

	/**
	 * Get settings.
	 *
	 * @return array
	 */
	public static function get_settings(): array {

		$settings = get_option(
			self::OPTION_NAME,
			array()
		);

		if ( empty( $settings ) ) {
			$settings = self::get_default_settings();
		}

		return $settings;
	}

	/**
	 * Save settings.
	 *
	 * @param array $settings Settings.
	 *
	 * @return bool
	 */
	public static function update_settings(
		array $settings
	): bool {

		return update_option(
			self::OPTION_NAME,
			$settings
		);
	}

	/**
	 * Get selected log level.
	 *
	 * @return string
	 */
	public static function get_log_level(): string {

		$log_level = get_option(
			self::LOG_LEVEL_OPTION_NAME,
			'recommended'
		);

		if ( ! is_string( $log_level ) ) {
			return 'recommended';
		}

		return self::sanitize_log_level( $log_level );
	}

	/**
	 * Save selected log level.
	 *
	 * @param string $log_level Log level.
	 *
	 * @return bool
	 */
	public static function update_log_level( string $log_level ): bool {

		return update_option(
			self::LOG_LEVEL_OPTION_NAME,
			self::sanitize_log_level( $log_level )
		);
	}

	/**
	 * Ensure log level is a valid known option.
	 *
	 * @param string $log_level Log level.
	 *
	 * @return string
	 */
	public static function sanitize_log_level( string $log_level ): string {

		if ( in_array( $log_level, self::ALLOWED_LOG_LEVELS, true ) ) {
			return $log_level;
		}

		return 'recommended';
	}

	/**
	 * Check event enabled.
	 *
	 * @param string $event Event.
	 * @param string $action Action.
	 *
	 * @return bool
	 */
	public static function is_enabled(
		string $event,
		string $action
	): bool {

		$settings = self::get_settings();

		if ( ! isset( $settings[ $event ] ) ) {
			return true;
		}

		if ( ! isset( $settings[ $event ][ $action ] ) ) {
			return true;
		}

		return (bool) $settings[ $event ][ $action ];
	}

	/**
	 * Get default settings.
	 *
	 * Delegates to the installer's "recommended" preset defaults so a
	 * fresh install behaves consistently with the default log level (see
	 * get_log_level()), instead of enabling every action as the
	 * "complete" preset would.
	 *
	 * @return array
	 */
	public static function get_default_settings(): array {

		return InstallationEvents::get_default_settings();
	}
}
