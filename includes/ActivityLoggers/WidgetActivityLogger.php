<?php // phpcs:ignore WordPress.Files.FileName.NotHyphenatedLowercase

namespace LogTrail\ActivityLoggers;

use LogTrail\Constants\Events;
use LogTrail\Constants\Actions;

defined( 'ABSPATH' ) || exit;

/**
 * Widget activity logger.
 */
class WidgetActivityLogger extends AbstractLogger {

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

		add_action(
			'update_option_sidebars_widgets',
			$this->guarded( array( $this, 'log_sidebar_widgets_updated' ) ),
			10,
			3
		);
	}

	/**
	 * Log widget/sidebar changes.
	 *
	 * @param array  $old_value Old value.
	 * @param array  $value New value.
	 * @param string $option Option name.
	 * @return void
	 */
	public function log_sidebar_widgets_updated(
		$old_value,
		$value,
		string $option
	): void {

		if ( $old_value === $value ) {
			return;
		}

		$this->insert_event_log(
			Events::WIDGET,
			Actions::UPDATE,
			array(
				'object_type' => 'sidebar_widgets',
				'object_id'   => 0,
				'user_id'     => get_current_user_id(),
				'message'     => 'Widgets updated.',
				'before_data' => wp_json_encode( $old_value ),
				'after_data'  => wp_json_encode( $value ),
				'context'     => array_merge(
					$this->get_common_context(),
					array(
						'option_name' => $option,
						'widget_id'   => $option,
					)
				),
			)
		);
	}
}
