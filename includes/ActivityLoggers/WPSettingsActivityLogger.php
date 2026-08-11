<?php // phpcs:ignore WordPress.Files.FileName.NotHyphenatedLowercase

namespace LogTrail\ActivityLoggers;

use LogTrail\Constants\Severity;
use LogTrail\Constants\Events;
use LogTrail\Constants\Actions;

defined( 'ABSPATH' ) || exit;

/**
 * WordPress settings activity logger.
 */
class WPSettingsActivityLogger extends AbstractLogger {

	/**
	 * WordPress version at construction time.
	 *
	 * Captured up front (rather than read at the time `_core_updated_successfully`
	 * fires) because by then `$wp_version` has already been overwritten in
	 * memory with the new version for the rest of the request.
	 *
	 * @var string
	 */
	protected $wp_version_before_update;

	/**
	 * Core WordPress settings worth logging, mapped to a human-readable label.
	 *
	 * Anything not listed here (transients, cron, autosave-ish options, etc.)
	 * is ignored so the log isn't flooded with noise from every option write.
	 *
	 * @var array<string, string>
	 */
	private const IMPORTANT_OPTIONS = array(
		'blogname'               => 'Site Title',
		'blogdescription'        => 'Tagline',
		'siteurl'                => 'Site URL',
		'home'                   => 'Home URL',
		'admin_email'            => 'Admin Email',
		'new_admin_email'        => 'Pending Admin Email',
		'users_can_register'     => 'Anyone Can Register',
		'default_role'           => 'Default Role',
		'timezone_string'        => 'Timezone',
		'gmt_offset'             => 'UTC Offset',
		'date_format'            => 'Date Format',
		'time_format'            => 'Time Format',
		'start_of_week'          => 'Week Starts On',
		'permalink_structure'    => 'Permalink Structure',
		'category_base'          => 'Category Base',
		'tag_base'               => 'Tag Base',
		'blog_public'            => 'Search Engine Visibility',
		'WPLANG'                 => 'Site Language',
		'default_comment_status' => 'Default Comment Status',
		'default_ping_status'    => 'Default Ping Status',
		'comment_registration'   => 'Require Registration to Comment',
		'comment_moderation'     => 'Comment Moderation',
		'show_on_front'          => 'Homepage Display',
		'page_on_front'          => 'Homepage',
		'page_for_posts'         => 'Posts Page',
		'auto_update_core_major' => 'Core Auto-Update (Major)',
		'auto_update_core_minor' => 'Core Auto-Update (Minor)',
		'auto_update_core_dev'   => 'Core Auto-Update (Dev)',
	);

	/**
	 * Constructor.
	 */
	public function __construct() {

		parent::__construct();

		$this->wp_version_before_update = $GLOBALS['wp_version'];

		$this->register_hooks();
	}

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	protected function register_hooks(): void {

		add_action( 'updated_option', $this->guarded( array( $this, 'log_option_updated' ) ), 10, 3 );

		add_action( '_core_updated_successfully', $this->guarded( array( $this, 'log_core_updated' ) ) );
	}

	/**
	 * Log option updates.
	 *
	 * @param string $option Option name.
	 * @param mixed  $old_value Old value.
	 * @param mixed  $value New value.
	 * @return void
	 */
	public function log_option_updated( string $option, $old_value, $value ): void {

		if ( ! array_key_exists( $option, self::IMPORTANT_OPTIONS ) ) {
			return;
		}

		$this->insert_event_log(
			Events::SETTINGS,
			Actions::UPDATE,
			array(
				'object_type' => 'option',
				'object_id'   => 0,
				'user_id'     => get_current_user_id(),
				'severity'    => Severity::WARNING,
				'message'     => sprintf(
					'"%s" setting updated.',
					self::IMPORTANT_OPTIONS[ $option ]
				),
				'before_data' => wp_json_encode( $old_value ),
				'after_data'  => wp_json_encode( $value ),
				'context'     => $this->get_common_context(),
			)
		);
	}

	/**
	 * Log a successful WordPress core update, manual or automatic.
	 *
	 * @param string $new_version Version WordPress was updated to.
	 * @return void
	 */
	public function log_core_updated( string $new_version ): void {

		$old_version = $this->wp_version_before_update;

		if ( $old_version === $new_version ) {
			return;
		}

		$is_auto_update = wp_doing_cron();

		$this->insert_event_log(
			Events::SETTINGS,
			Actions::CORE_UPDATE,
			array(
				'object_type' => 'core',
				'object_id'   => 0,
				'user_id'     => get_current_user_id(),
				'severity'    => Severity::WARNING,
				'message'     => $is_auto_update
					? sprintf( 'WordPress auto-updated to %s from %s.', $new_version, $old_version )
					: sprintf( 'WordPress updated to %s from %s.', $new_version, $old_version ),
				'before_data' => wp_json_encode( array( 'wp_version' => $old_version ) ),
				'after_data'  => wp_json_encode( array( 'wp_version' => $new_version ) ),
				'context'     => array_merge(
					$this->get_common_context(),
					array( 'auto_update' => $is_auto_update )
				),
			)
		);
	}
}
