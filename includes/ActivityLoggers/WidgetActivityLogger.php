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

		$diff = $this->diff_sidebars( (array) $old_value, (array) $value );

		if ( empty( $diff['before'] ) && empty( $diff['after'] ) ) {
			return;
		}

		$message = ! empty( $diff['sidebars'] )
			? sprintf( 'Widgets updated in: %s.', implode( ', ', $diff['sidebars'] ) )
			: 'Widgets updated.';

		$this->insert_event_log(
			Events::WIDGET,
			Actions::UPDATE,
			array(
				'object_type' => 'sidebar_widgets',
				'object_id'   => 0,
				'user_id'     => get_current_user_id(),
				'message'     => $message,
				'before_data' => wp_json_encode( $diff['before'] ),
				'after_data'  => wp_json_encode( $diff['after'] ),
				'context'     => array_merge(
					$this->get_common_context(),
					array(
						'option_name' => $option,
					)
				),
			)
		);
	}

	/**
	 * Diff two `sidebars_widgets` snapshots down to just the widget areas
	 * that actually changed, skipping WordPress-internal bookkeeping keys
	 * (`array_version`) that are always present regardless of whether
	 * anything meaningful changed - and skipping any area whose widget
	 * list is unchanged, so a save that only touched one sidebar doesn't
	 * dump every other sidebar on the site into the diff.
	 *
	 * @param array $old_widgets Previous `sidebars_widgets` value.
	 * @param array $new_widgets New `sidebars_widgets` value.
	 * @return array{before: array<string, array>, after: array<string, array>, sidebars: string[]}
	 */
	protected function diff_sidebars( array $old_widgets, array $new_widgets ): array {

		unset( $old_widgets['array_version'], $new_widgets['array_version'] );

		$sidebar_ids = array_unique( array_merge( array_keys( $old_widgets ), array_keys( $new_widgets ) ) );

		$before   = array();
		$after    = array();
		$sidebars = array();

		foreach ( $sidebar_ids as $sidebar_id ) {

			$old = isset( $old_widgets[ $sidebar_id ] ) ? (array) $old_widgets[ $sidebar_id ] : array();
			$new = isset( $new_widgets[ $sidebar_id ] ) ? (array) $new_widgets[ $sidebar_id ] : array();

			if ( $old === $new ) {
				continue;
			}

			$before[ $sidebar_id ] = $old;
			$after[ $sidebar_id ]  = $new;
			$sidebars[]            = $this->sidebar_label( (string) $sidebar_id );
		}

		return array(
			'before'   => $before,
			'after'    => $after,
			'sidebars' => $sidebars,
		);
	}

	/**
	 * Get a human-readable label for a widget area ID, falling back to
	 * the raw ID for any area not currently registered (e.g. one from a
	 * theme/plugin that's since been switched away from or deactivated).
	 *
	 * @param string $sidebar_id Sidebar/widget-area ID.
	 * @return string
	 */
	protected function sidebar_label( string $sidebar_id ): string {

		if ( 'wp_inactive_widgets' === $sidebar_id ) {
			return __( 'Inactive Widgets', 'logtrail' );
		}

		global $wp_registered_sidebars;

		return $wp_registered_sidebars[ $sidebar_id ]['name'] ?? $sidebar_id;
	}
}
