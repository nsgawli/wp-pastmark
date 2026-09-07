<?php // phpcs:ignore WordPress.Files.FileName.NotHyphenatedLowercase

namespace Pastmark\ActivityLoggers\BbPress;

use Pastmark\ActivityLoggers\AbstractLogger;
use Pastmark\Constants\Actions;
use Pastmark\Constants\Events;
use Pastmark\Constants\Severity;

defined( 'ABSPATH' ) || exit;

/**
 * The bbPress settings activity logger (PM-171, Sprint 11).
 *
 * BbPress' own Settings screen (`includes/admin/settings.php`) is built
 * entirely on the standard WordPress Settings API (`register_setting()`
 * for every `_bbp_*` option, confirmed against the real installed source)
 * - it writes through `update_option()` exactly like WordPress core's own
 * settings, so this bridges the same way `WPSettingsActivityLogger`
 * already handles core settings (PM-151's precedent for reusing an
 * existing `updated_option` bridge rather than inventing a bbPress-
 * specific save hook that doesn't exist).
 *
 * `IMPORTANT_OPTIONS` is a curated subset (not bbPress' full ~30-option
 * settings surface) - the same "worth logging, not exhaustive" judgment
 * call `WPSettingsActivityLogger::IMPORTANT_OPTIONS` already makes for
 * core settings.
 */
class SettingsActivityLogger extends AbstractLogger {

	/**
	 * {@inheritDoc}
	 *
	 * @var string
	 */
	protected $integration = 'bbpress';

	/**
	 * BbPress settings worth logging, mapped to a human-readable label.
	 *
	 * `_bbp_allow_anonymous` is flagged `Severity::WARNING` in
	 * `log_option_updated()` below regardless of direction (enabling
	 * *or* disabling guest posting is worth a site owner's attention) -
	 * mirrors this codebase's existing convention of elevating severity
	 * for security-relevant settings changes.
	 *
	 * @var array<string, string>
	 */
	private const IMPORTANT_OPTIONS = array(
		'_bbp_default_role'           => 'Auto Role for New Users',
		'_bbp_allow_anonymous'        => 'Allow Anonymous Posting',
		'_bbp_allow_global_access'    => 'Auto Role for New Site Users',
		'_bbp_allow_content_throttle' => 'Enable Post Throttling',
		'_bbp_throttle_time'          => 'Posting Throttle Time (seconds)',
		'_bbp_allow_content_edit'     => 'Allow Users To Edit Their Posts',
		'_bbp_edit_lock'              => 'Disallow Post Editing After (minutes)',
		'_bbp_allow_revisions'        => 'Allow Topic and Reply Revisions',
		'_bbp_allow_topic_tags'       => 'Allow Topic Tags',
		'_bbp_allow_threaded_replies' => 'Allow Threaded Replies',
		'_bbp_thread_replies_depth'   => 'Threaded Reply Depth',
		'_bbp_enable_favorites'       => 'Allow Favorites',
		'_bbp_enable_subscriptions'   => 'Allow Subscriptions',
		'_bbp_allow_search'           => 'Allow Forum Search',
		'_bbp_allow_forum_mods'       => 'Allow Forum Moderators',
		'_bbp_allow_super_mods'       => 'Allow Super Moderators',
		'_bbp_theme_package_id'       => 'Forum Theme Package',
		'_bbp_topics_per_page'        => 'Topics Per Page',
		'_bbp_replies_per_page'       => 'Replies Per Page',
	);

	/**
	 * Constructor.
	 */
	public function __construct() {

		parent::__construct();

		$this->register_hooks();
	}

	/**
	 * Register hooks. No `register_events()` here - `ForumActivityLogger`
	 * declares the shared `bbpress` event group (including this logger's
	 * own `Actions::UPDATE` action) since `BbPressIntegration` constructs
	 * it first.
	 *
	 * @return void
	 */
	protected function register_hooks(): void {

		add_action( 'updated_option', $this->guarded( array( $this, 'log_option_updated' ) ), 10, 3 );
	}

	/**
	 * Log a bbPress settings change.
	 *
	 * @param string $option    Option name.
	 * @param mixed  $old_value Previous value.
	 * @param mixed  $value     New value.
	 * @return void
	 */
	public function log_option_updated( string $option, $old_value, $value ): void {

		if ( ! array_key_exists( $option, self::IMPORTANT_OPTIONS ) ) {
			return;
		}

		$label = self::IMPORTANT_OPTIONS[ $option ];

		$this->insert_event_log(
			Events::BBPRESS,
			Actions::UPDATE,
			array(
				'object_type' => 'option',
				'object_id'   => 0,
				'severity'    => ( '_bbp_allow_anonymous' === $option ) ? Severity::WARNING : Severity::INFO,
				'message'     => sprintf( '"%s" bbPress setting updated.', $label ),
				// Keyed by the setting's display label, matching
				// `WPSettingsActivityLogger::log_option_updated()`'s own
				// established shape for the admin UI's diff table.
				'before_data' => wp_json_encode( array( $label => $old_value ) ),
				'after_data'  => wp_json_encode( array( $label => $value ) ),
				'context'     => $this->get_common_context(),
			)
		);
	}
}
