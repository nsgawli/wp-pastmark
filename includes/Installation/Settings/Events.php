<?php // phpcs:ignore WordPress.Files.FileName.NotHyphenatedLowercase
namespace Pastmark\Installation\Settings;

use Pastmark\EventSettings\EventPresets;
use Pastmark\EventSettings\EventRegistry;
use Pastmark\EventSettings\EventSettings;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Event settings installer.
 */
class Events {

	/**
	 * Install default settings.
	 *
	 * Persists the "recommended" preset explicitly so the stored settings
	 * and log level agree from the start, instead of relying on the two
	 * separate lazy defaults in EventSettings::get_settings() and
	 * EventSettings::get_log_level().
	 *
	 * @return void
	 */
	public static function install() {

		update_option( EventSettings::OPTION_NAME, self::get_default_settings() );

		update_option( EventSettings::LOG_LEVEL_OPTION_NAME, 'recommended' );
	}

	/**
	 * Get default settings.
	 *
	 * Defaults to the "recommended" preset so a fresh install behaves
	 * consistently with the default log level, instead of enabling every
	 * action the way the "complete" preset does.
	 *
	 * @return array
	 */
	public static function get_default_settings() {

		$settings = array();

		$events      = EventRegistry::get_events();
		$recommended = EventPresets::get_presets()['recommended'];

		foreach ( $events as $event_key => $event ) {

			$settings[ $event_key ] = array();

			$enabled_actions = $recommended[ $event_key ] ?? array();

			foreach ( $event['actions'] as $action ) {

				$settings[ $event_key ][ $action['key'] ] = in_array(
					$action['key'],
					$enabled_actions,
					true
				);
			}
		}

		return $settings;
	}
}
