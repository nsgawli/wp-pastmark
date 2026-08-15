<?php // phpcs:ignore WordPress.Files.FileName.NotHyphenatedLowercase

namespace Pastmark\ActivityLoggers;

use Pastmark\Constants\Severity;
use Pastmark\Constants\Events;
use Pastmark\Constants\Actions;
use WP_Theme;

defined( 'ABSPATH' ) || exit;

/**
 * Theme activity logger.
 */
class ThemeActivityLogger extends AbstractLogger {

	/**
	 * Theme data captured before deletion, keyed by stylesheet.
	 *
	 * @var array<string, array>
	 */
	protected $pending_theme_delete_data = array();

	/**
	 * Details of an in-progress theme editor file save, captured before the
	 * request writes to disk so the shutdown handler can diff it.
	 *
	 * @var array|null
	 */
	protected $pending_theme_file_edit = null;

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

		add_action( 'switch_theme', $this->guarded( array( $this, 'log_theme_switched' ) ), 10, 3 );

		add_action(
			'upgrader_process_complete',
			$this->guarded( array( $this, 'log_upgrader_process_complete' ) ),
			10,
			2
		);

		add_action( 'delete_theme', $this->guarded( array( $this, 'capture_theme_before_delete' ) ) );

		add_action( 'deleted_theme', $this->guarded( array( $this, 'log_theme_deleted' ) ), 10, 2 );

		/*
		 * `network_enable_theme()`/`network_disable_theme()` are Multisite-only
		 * (they no-op otherwise) and always go through `update_site_option()`,
		 * which fires the `*_site_option_*` hooks rather than `*_option_*`.
		 */
		add_action(
			'add_site_option_allowedthemes',
			$this->guarded( array( $this, 'log_theme_network_state_option_added' ) ),
			10,
			2
		);

		add_action(
			'update_site_option_allowedthemes',
			$this->guarded( array( $this, 'log_theme_network_state_option_changed' ) ),
			10,
			3
		);

		/*
		 * The theme editor's save handler has no hook of its own, so the file
		 * is snapshotted just before the core AJAX handler runs (priority 0,
		 * ahead of core's priority 1) and diffed on `shutdown` — core's
		 * handler ends the request with wp_die(), which still runs shutdown
		 * callbacks, and comparing before/after content doubles as an
		 * implicit success check (a rejected save never touches the file).
		 */
		add_action( 'wp_ajax_edit-theme-plugin-file', $this->guarded( array( $this, 'capture_theme_file_before_edit' ) ), 0 );
	}

	/**
	 * Log theme switch.
	 *
	 * @param string   $new_name New theme name.
	 * @param WP_Theme $new_theme New theme object.
	 * @param WP_Theme $old_theme Old theme object.
	 * @return void
	 */
	public function log_theme_switched(
		string $new_name,
		WP_Theme $new_theme,
		WP_Theme $old_theme
	): void {

		$this->insert_event_log(
			Events::THEME,
			Actions::SWITCH,
			array(
				'object_type' => 'theme',
				'object_id'   => 0,
				'user_id'     => get_current_user_id(),
				'message'     => sprintf(
					'Theme switched from "%s" to "%s".',
					$old_theme->get( 'Name' ),
					$new_name
				),
				'before_data' => wp_json_encode(
					array(
						'name'    => $old_theme->get( 'Name' ),
						'version' => $old_theme->get( 'Version' ),
					)
				),
				'after_data'  => wp_json_encode(
					array(
						'name'    => $new_theme->get( 'Name' ),
						'version' => $new_theme->get( 'Version' ),
					)
				),
				'severity'    => Severity::WARNING,
				'context'     => array_merge(
					$this->get_common_context(),
					array(
						'theme' => $new_theme->get_stylesheet(),
					)
				),
			)
		);
	}

	/**
	 * Dispatch a completed upgrader run to the install or upgrade handler.
	 *
	 * Fires for plugins, themes, translations and core; only theme installs
	 * and updates are relevant here.
	 *
	 * @param \WP_Upgrader $upgrader Upgrader instance that just finished running.
	 * @param array        $hook_extra Details about what was installed/updated.
	 * @return void
	 */
	public function log_upgrader_process_complete( $upgrader, $hook_extra ): void {

		if ( 'theme' !== ( $hook_extra['type'] ?? '' ) ) {
			return;
		}

		if ( 'install' === ( $hook_extra['action'] ?? '' ) ) {
			$this->log_theme_installed( $upgrader );
			return;
		}

		if ( 'update' === ( $hook_extra['action'] ?? '' ) ) {
			$this->log_themes_upgraded( $hook_extra );
		}
	}

	/**
	 * Log a fresh theme install.
	 *
	 * @param \WP_Upgrader $upgrader Upgrader instance that just finished running.
	 * @return void
	 */
	protected function log_theme_installed( $upgrader ): void {

		$theme = method_exists( $upgrader, 'theme_info' ) ? $upgrader->theme_info() : false;

		if ( ! $theme || ! $theme->exists() ) {
			return;
		}

		$this->insert_event_log(
			Events::THEME,
			Actions::INSTALL,
			array(
				'object_type' => 'theme',
				'object_id'   => 0,
				'user_id'     => get_current_user_id(),
				'message'     => sprintf(
					'Theme "%s" installed.',
					$theme->get( 'Name' )
				),
				'after_data'  => wp_json_encode( $this->prepare_theme_data( $theme ) ),
				'context'     => array_merge(
					$this->get_common_context(),
					array( 'theme' => $theme->get_stylesheet() )
				),
			)
		);
	}

	/**
	 * Log one or more theme upgrades (single or bulk).
	 *
	 * @param array $hook_extra Details about the completed update.
	 * @return void
	 */
	protected function log_themes_upgraded( array $hook_extra ): void {

		$stylesheets = ! empty( $hook_extra['themes'] ) ? (array) $hook_extra['themes'] : array();

		if ( empty( $stylesheets ) && ! empty( $hook_extra['theme'] ) ) {
			$stylesheets = array( $hook_extra['theme'] );
		}

		if ( empty( $stylesheets ) ) {
			return;
		}

		// Still holds pre-upgrade versions; cleared shortly after this hook fires.
		$update_transient = get_site_transient( 'update_themes' );
		$checked_versions = is_object( $update_transient ) && isset( $update_transient->checked )
			? (array) $update_transient->checked
			: array();

		foreach ( $stylesheets as $stylesheet ) {

			$theme = wp_get_theme( $stylesheet );

			$name        = $theme->exists() ? $theme->get( 'Name' ) : $stylesheet;
			$old_version = $checked_versions[ $stylesheet ] ?? '';
			$new_version = $theme->exists() ? $theme->get( 'Version' ) : '';

			$message = ( $old_version && $old_version !== $new_version )
				? sprintf( 'Theme "%s" upgraded from version %s to %s.', $name, $old_version, $new_version )
				: sprintf( 'Theme "%s" upgraded to version %s.', $name, $new_version );

			$this->insert_event_log(
				Events::THEME,
				Actions::UPDATE,
				array(
					'object_type' => 'theme',
					'object_id'   => 0,
					'user_id'     => get_current_user_id(),
					'message'     => $message,
					'before_data' => wp_json_encode( array( 'version' => $old_version ) ),
					'after_data'  => wp_json_encode( $this->prepare_theme_data( $theme ) ),
					'context'     => array_merge(
						$this->get_common_context(),
						array( 'theme' => $stylesheet )
					),
				)
			);
		}
	}

	/**
	 * Capture theme data before it's deleted from disk.
	 *
	 * @param string $stylesheet Stylesheet of the theme being deleted.
	 * @return void
	 */
	public function capture_theme_before_delete( string $stylesheet ): void {

		$theme = wp_get_theme( $stylesheet );

		$this->pending_theme_delete_data[ $stylesheet ] = $theme->exists()
			? $this->prepare_theme_data( $theme )
			: array(
				'name'    => $stylesheet,
				'version' => '',
				'author'  => '',
			);
	}

	/**
	 * Log theme deletion, if it actually succeeded.
	 *
	 * @param string $stylesheet Stylesheet of the theme that was deleted.
	 * @param bool   $deleted Whether the deletion succeeded.
	 * @return void
	 */
	public function log_theme_deleted( string $stylesheet, bool $deleted ): void {

		$data = $this->pending_theme_delete_data[ $stylesheet ] ?? array(
			'name'    => $stylesheet,
			'version' => '',
			'author'  => '',
		);

		unset( $this->pending_theme_delete_data[ $stylesheet ] );

		if ( ! $deleted ) {
			return;
		}

		$this->insert_event_log(
			Events::THEME,
			Actions::DELETE,
			array(
				'object_type' => 'theme',
				'object_id'   => 0,
				'user_id'     => get_current_user_id(),
				'severity'    => Severity::WARNING,
				'message'     => sprintf(
					'Theme "%s" uninstalled.',
					$data['name']
				),
				'before_data' => wp_json_encode( $data ),
				'context'     => array_merge(
					$this->get_common_context(),
					array( 'theme' => $stylesheet )
				),
			)
		);
	}

	/**
	 * Log the network theme allow-list being created for the first time (Multisite).
	 *
	 * @param string $option Option name.
	 * @param mixed  $value New option value.
	 * @return void
	 */
	public function log_theme_network_state_option_added( $option, $value ): void {

		$this->log_theme_network_state_diff( array(), (array) $value );
	}

	/**
	 * Log the network theme allow-list changing (Multisite).
	 *
	 * @param string $option Option name.
	 * @param mixed  $value New option value.
	 * @param mixed  $old_value Previous option value.
	 * @return void
	 */
	public function log_theme_network_state_option_changed( $option, $value, $old_value ): void {

		$this->log_theme_network_state_diff( (array) $old_value, (array) $value );
	}

	/**
	 * Diff the `allowedthemes` map and log each theme that toggled.
	 *
	 * Stored as `[ stylesheet => true ]`, so the diff runs on the keys, not
	 * the (identical) values.
	 *
	 * @param array $old_map Previous allow-list.
	 * @param array $new_map New allow-list.
	 * @return void
	 */
	protected function log_theme_network_state_diff( array $old_map, array $new_map ): void {

		$old_stylesheets = array_keys( array_filter( $old_map ) );
		$new_stylesheets = array_keys( array_filter( $new_map ) );

		foreach ( array_diff( $new_stylesheets, $old_stylesheets ) as $stylesheet ) {
			$this->log_theme_network_state( (string) $stylesheet, true );
		}

		foreach ( array_diff( $old_stylesheets, $new_stylesheets ) as $stylesheet ) {
			$this->log_theme_network_state( (string) $stylesheet, false );
		}
	}

	/**
	 * Insert a network activate/deactivate log entry for a single theme.
	 *
	 * @param string $stylesheet Theme stylesheet.
	 * @param bool   $enabled Whether the theme was enabled or disabled network-wide.
	 * @return void
	 */
	protected function log_theme_network_state( string $stylesheet, bool $enabled ): void {

		$theme = wp_get_theme( $stylesheet );
		$data  = $theme->exists()
			? $this->prepare_theme_data( $theme )
			: array(
				'name'    => $stylesheet,
				'version' => '',
				'author'  => '',
			);

		$this->insert_event_log(
			Events::THEME,
			$enabled ? Actions::ACTIVATE : Actions::DEACTIVATE,
			array(
				'object_type' => 'theme',
				'object_id'   => 0,
				'user_id'     => get_current_user_id(),
				'severity'    => Severity::WARNING,
				'message'     => sprintf(
					'Theme "%s" %s network-wide.',
					$data['name'],
					$enabled ? 'enabled' : 'disabled'
				),
				// Mirrors PluginActivityLogger::log_plugin_activated()/
				// log_plugin_deactivated(): the toggled-on side gets
				// after_data, the toggled-off side gets before_data, so the
				// diff table shows what was (de)activated.
				'after_data'  => $enabled ? wp_json_encode( $data ) : '',
				'before_data' => $enabled ? '' : wp_json_encode( $data ),
				'context'     => array_merge(
					$this->get_common_context(),
					array(
						'theme'        => $stylesheet,
						'network_wide' => true,
					)
				),
			)
		);
	}

	/**
	 * Snapshot a theme editor file just before the core AJAX handler writes
	 * to it, so the shutdown handler can tell whether the save went through.
	 *
	 * @return void
	 */
	public function capture_theme_file_before_edit(): void {

		// phpcs:disable WordPress.Security.NonceVerification.Missing -- Read-only observation; core's own handler (which runs right after this, at priority 1) verifies the nonce before it writes anything.
		if ( empty( $_POST['theme'] ) || ! isset( $_POST['file'] ) ) {
			return;
		}

		$stylesheet = sanitize_text_field( wp_unslash( $_POST['theme'] ) );
		$file       = sanitize_text_field( wp_unslash( $_POST['file'] ) );
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		if ( 0 !== validate_file( $stylesheet ) || 0 !== validate_file( $file ) ) {
			return;
		}

		$theme = wp_get_theme( $stylesheet );

		if ( ! $theme->exists() ) {
			return;
		}

		$real_file = $theme->get_stylesheet_directory() . '/' . $file;

		if ( ! is_file( $real_file ) ) {
			return;
		}

		$this->pending_theme_file_edit = array(
			'stylesheet' => $stylesheet,
			'theme_name' => $theme->get( 'Name' ),
			'file'       => $file,
			'real_file'  => $real_file,
			'content'    => file_get_contents( $real_file ), // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local theme file, not a remote URL.
			'user_id'    => get_current_user_id(),
		);

		add_action( 'shutdown', $this->guarded( array( $this, 'log_theme_file_edited' ) ) );
	}

	/**
	 * Diff the captured theme file against its current contents and log the
	 * edit if it actually changed.
	 *
	 * Runs on `shutdown` since the core AJAX handler ends the request with
	 * `wp_die()` before a normal callback could observe the result.
	 *
	 * @return void
	 */
	public function log_theme_file_edited(): void {

		if ( ! $this->pending_theme_file_edit ) {
			return;
		}

		$pending                       = $this->pending_theme_file_edit;
		$this->pending_theme_file_edit = null;

		if ( ! is_file( $pending['real_file'] ) ) {
			return;
		}

		$new_content = file_get_contents( $pending['real_file'] ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local theme file, not a remote URL.

		if ( $new_content === $pending['content'] ) {
			return;
		}

		$this->insert_event_log(
			Events::THEME,
			Actions::FILE_EDIT,
			array(
				'object_type' => 'theme',
				'object_id'   => 0,
				'user_id'     => $pending['user_id'],
				'severity'    => Severity::WARNING,
				'message'     => sprintf(
					'File "%s" edited for theme "%s" using the theme editor.',
					$pending['file'],
					$pending['theme_name']
				),
				'before_data' => wp_json_encode(
					array(
						'checksum' => md5( $pending['content'] ),
						'size'     => strlen( $pending['content'] ),
					)
				),
				'after_data'  => wp_json_encode(
					array(
						'checksum' => md5( $new_content ),
						'size'     => strlen( $new_content ),
					)
				),
				'context'     => array_merge(
					$this->get_common_context(),
					array(
						'theme' => $pending['stylesheet'],
						'file'  => $pending['file'],
					)
				),
			)
		);
	}

	/**
	 * Prepare theme data for storage.
	 *
	 * @param WP_Theme $theme Theme object.
	 * @return array
	 */
	protected function prepare_theme_data( WP_Theme $theme ): array {

		return array(
			'name'    => $theme->get( 'Name' ),
			'version' => $theme->get( 'Version' ),
			'author'  => wp_strip_all_tags( $theme->get( 'Author' ) ),
		);
	}
}
