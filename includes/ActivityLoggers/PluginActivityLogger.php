<?php // phpcs:ignore WordPress.Files.FileName.NotHyphenatedLowercase

namespace LogTrail\ActivityLoggers;

use LogTrail\Constants\Severity;
use LogTrail\Constants\Events;
use LogTrail\Constants\Actions;
use LogTrail\Utils\Helpers;

defined( 'ABSPATH' ) || exit;

/**
 * Plugin activity logger.
 */
class PluginActivityLogger extends AbstractLogger {

	/**
	 * Constructor.
	 */
	public function __construct() {

		parent::__construct();

		$this->register_hooks();
	}

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	protected function register_hooks(): void {

		add_action( 'activated_plugin', $this->guarded( array( $this, 'log_plugin_activated' ) ), 10, 2 );

		add_action( 'deactivated_plugin', $this->guarded( array( $this, 'log_plugin_deactivated' ) ), 10, 2 );

		add_action( 'deleted_plugin', $this->guarded( array( $this, 'log_plugin_deleted' ) ), 10, 2 );

		add_action( 'upgrader_process_complete', $this->guarded( array( $this, 'log_upgrader_process_complete' ) ), 10, 2 );

		add_filter( 'upgrader_install_package_result', $this->guarded( array( $this, 'log_plugin_install_failed' ) ), 10, 2 );
	}

	/**
	 * Log plugin activation.
	 *
	 * @param string $plugin Plugin path.
	 * @param bool   $network_wide Network wide.
	 * @return void
	 */
	public function log_plugin_activated( string $plugin, bool $network_wide ): void {

		$data = $this->get_plugin_data( $plugin );

		$this->insert_event_log(
			Events::PLUGIN,
			Actions::ACTIVATE,
			array(
				'object_type' => 'plugin',
				'object_id'   => 0,
				'user_id'     => get_current_user_id(),
				'action'      => Actions::ACTIVATE,
				'message'     => sprintf(
					'Plugin "%s" activated.',
					$data['name']
				),
				'after_data'  => wp_json_encode( $data ),
				'severity'    => Severity::WARNING,
				'context'     => array_merge(
					$this->get_common_context(),
					array(
						'plugin'       => $plugin,
						'plugin_file'  => $plugin,
						'network_wide' => $network_wide,
					)
				),
			)
		);
	}

	/**
	 * Log plugin deactivation.
	 *
	 * @param string $plugin Plugin path.
	 * @param bool   $network_wide Network wide.
	 * @return void
	 */
	public function log_plugin_deactivated( string $plugin, bool $network_wide ): void {

		$data = $this->get_plugin_data( $plugin );

		$this->insert_event_log(
			Events::PLUGIN,
			Actions::DEACTIVATE,
			array(
				'object_type' => 'plugin',
				'object_id'   => 0,
				'user_id'     => get_current_user_id(),
				'message'     => sprintf(
					'Plugin "%s" deactivated.',
					$data['name']
				),
				'before_data' => wp_json_encode( $data ),
				'severity'    => Severity::WARNING,
				'context'     => array_merge(
					$this->get_common_context(),
					array(
						'plugin'       => $plugin,
						'plugin_file'  => $plugin,
						'network_wide' => $network_wide,
					)
				),
			)
		);
	}

	/**
	 * Log plugin deletion.
	 *
	 * @param string $plugin Plugin path.
	 * @param bool   $deleted Whether the plugin deletion was successful.
	 * @return void
	 */
	public function log_plugin_deleted( string $plugin, bool $deleted ): void {

		if ( ! $deleted ) {
			return;
		}

		$this->insert_event_log(
			Events::PLUGIN,
			Actions::DELETE,
			array(
				'object_type' => 'plugin',
				'object_id'   => 0,
				'user_id'     => get_current_user_id(),
				'message'     => sprintf(
					'Plugin "%s" deleted.',
					$plugin
				),
				'before_data' => wp_json_encode( array( 'plugin' => $plugin ) ),
				'context'     => array_merge(
					$this->get_common_context(),
					array(
						'plugin'       => $plugin,
						'plugin_file'  => $plugin,
						'network_wide' => false,
					)
				),
				'severity'    => Severity::WARNING,
			)
		);
	}

	/**
	 * Dispatch a completed upgrader run to the install or upgrade handler.
	 *
	 * Fires for plugins, themes, translations and core; only plugin installs
	 * and updates are relevant here.
	 *
	 * @param \WP_Upgrader $upgrader Upgrader instance that just finished running.
	 * @param array        $hook_extra Details about what was installed/updated.
	 * @return void
	 */
	public function log_upgrader_process_complete( $upgrader, $hook_extra ): void {

		if ( 'plugin' !== ( $hook_extra['type'] ?? '' ) ) {
			return;
		}

		if ( 'install' === ( $hook_extra['action'] ?? '' ) ) {
			$this->log_plugin_installed( $upgrader );
			return;
		}

		if ( 'update' === ( $hook_extra['action'] ?? '' ) ) {
			$this->log_plugins_upgraded( $hook_extra );
		}
	}

	/**
	 * Log a fresh plugin install.
	 *
	 * @param \WP_Upgrader $upgrader Upgrader instance that just finished running.
	 * @return void
	 */
	protected function log_plugin_installed( $upgrader ): void {

		$plugin = method_exists( $upgrader, 'plugin_info' ) ? $upgrader->plugin_info() : false;

		if ( ! $plugin ) {
			return;
		}

		$data = $this->get_plugin_data( $plugin );

		$this->insert_event_log(
			Events::PLUGIN,
			Actions::INSTALL,
			array(
				'object_type' => 'plugin',
				'object_id'   => 0,
				'user_id'     => get_current_user_id(),
				'message'     => sprintf(
					'Plugin "%s" installed.',
					! empty( $data['name'] ) ? $data['name'] : $plugin
				),
				'after_data'  => wp_json_encode( $data ),
				'context'     => array_merge(
					$this->get_common_context(),
					array(
						'plugin'      => $plugin,
						'plugin_file' => $plugin,
					)
				),
			)
		);
	}

	/**
	 * Log one or more plugin upgrades (single or bulk).
	 *
	 * @param array $hook_extra Details about the completed update.
	 * @return void
	 */
	protected function log_plugins_upgraded( array $hook_extra ): void {

		$plugins = ! empty( $hook_extra['plugins'] ) ? (array) $hook_extra['plugins'] : array();

		if ( empty( $plugins ) && ! empty( $hook_extra['plugin'] ) ) {
			$plugins = array( $hook_extra['plugin'] );
		}

		if ( empty( $plugins ) ) {
			return;
		}

		// Still holds pre-upgrade versions; cleared shortly after this hook fires.
		$update_transient = get_site_transient( 'update_plugins' );
		$checked_versions = is_object( $update_transient ) && isset( $update_transient->checked )
			? (array) $update_transient->checked
			: array();

		foreach ( $plugins as $plugin ) {

			$data = $this->get_plugin_data( $plugin );

			$old_version = $checked_versions[ $plugin ] ?? '';
			$new_version = $data['version'] ?? '';
			$plugin_name = ! empty( $data['name'] ) ? $data['name'] : $plugin;

			$message = ( $old_version && $old_version !== $new_version )
				? sprintf( 'Plugin "%s" upgraded from version %s to %s.', $plugin_name, $old_version, $new_version )
				: sprintf( 'Plugin "%s" upgraded to version %s.', $plugin_name, $new_version );

			$this->insert_event_log(
				Events::PLUGIN,
				Actions::UPDATE,
				array(
					'object_type' => 'plugin',
					'object_id'   => 0,
					'user_id'     => get_current_user_id(),
					'message'     => $message,
					'before_data' => wp_json_encode( array( 'version' => $old_version ) ),
					'after_data'  => wp_json_encode( $data ),
					'context'     => array_merge(
						$this->get_common_context(),
						array(
							'plugin'      => $plugin,
							'plugin_file' => $plugin,
						)
					),
				)
			);
		}
	}

	/**
	 * Log a failed plugin installation.
	 *
	 * Must return `$result` unmodified since this hooks a filter.
	 *
	 * @param array|\WP_Error $result Result of the install step.
	 * @param array           $hook_extra Details about the install attempt.
	 * @return array|\WP_Error
	 */
	public function log_plugin_install_failed( $result, $hook_extra ) {

		if ( ! is_wp_error( $result ) ) {
			return $result;
		}

		if ( 'plugin' !== ( $hook_extra['type'] ?? '' ) || 'install' !== ( $hook_extra['action'] ?? '' ) ) {
			return $result;
		}

		$this->insert_event_log(
			Events::PLUGIN,
			Actions::INSTALL_FAILED,
			array(
				'object_type' => 'plugin',
				'object_id'   => 0,
				'user_id'     => get_current_user_id(),
				'severity'    => Severity::ERROR,
				'message'     => sprintf(
					'Plugin installation failed: %s',
					$result->get_error_message()
				),
				'context'     => array_merge(
					$this->get_common_context(),
					array( 'error_code' => $result->get_error_code() )
				),
			)
		);

		return $result;
	}

	/**
	 * Get plugin data.
	 *
	 * @param string $plugin Plugin path.
	 * @return array
	 */
	protected function get_plugin_data( string $plugin ): array {

		return Helpers::get_plugin_data( $plugin );
	}
}
