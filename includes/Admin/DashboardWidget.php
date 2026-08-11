<?php // phpcs:ignore WordPress.Files.FileName.NotHyphenatedLowercase
namespace LogTrail\Admin;

use LogTrail\Models\LogTrail_Logs;
use LogTrail\Utils\Helpers;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WordPress dashboard widget.
 */
class DashboardWidget {

	/**
	 * Initialize hooks.
	 *
	 * @return void
	 */
	public static function init() {

		add_action( 'wp_dashboard_setup', array( __CLASS__, 'register_widget' ) );
	}

	/**
	 * Register dashboard widget when enabled.
	 *
	 * @return void
	 */
	public static function register_widget() {

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( ! self::is_enabled() ) {
			return;
		}

		wp_add_dashboard_widget(
			'logtrail_latest_logs_widget',
			esc_html__( 'Latest Activity Logs', 'logtrail' ),
			array( __CLASS__, 'render_widget' )
		);
	}

	/**
	 * Render the latest logs dashboard widget.
	 *
	 * @return void
	 */
	public static function render_widget() {

		$model = new LogTrail_Logs();

		$logs = $model->get_logs(
			array(
				'number'  => 5,
				'orderby' => 'timestamp',
				'order'   => 'DESC',
			)
		);

		if ( empty( $logs ) ) {
			echo '<p>' . esc_html__( 'No activity logs found.', 'logtrail' ) . '</p>';

			return;
		}

		echo '<table class="widefat striped">';
		echo '<thead><tr>';
		echo '<th>' . esc_html__( 'Time', 'logtrail' ) . '</th>';
		echo '<th>' . esc_html__( 'User', 'logtrail' ) . '</th>';
		echo '<th>' . esc_html__( 'Event', 'logtrail' ) . '</th>';
		echo '<th>' . esc_html__( 'Message', 'logtrail' ) . '</th>';
		echo '</tr></thead>';
		echo '<tbody>';

		foreach ( $logs as $log ) {
			$user_label = esc_html__( 'System', 'logtrail' );

			if ( ! empty( $log->user_id ) ) {
				$user = get_userdata( (int) $log->user_id );

				if ( $user ) {
					$user_label = $user->display_name;
				}
			}

			$timestamp = Helpers::format_timestamp_for_display( (string) $log->timestamp );

			echo '<tr>';
			echo '<td>' . esc_html( $timestamp ) . '</td>';
			echo '<td>' . esc_html( $user_label ) . '</td>';
			echo '<td>' . esc_html( $log->event_type ) . '</td>';
			echo '<td>' . esc_html( wp_trim_words( wp_strip_all_tags( (string) $log->message ), 14, '...' ) ) . '</td>';
			echo '</tr>';
		}

		echo '</tbody>';
		echo '</table>';

		echo '<p style="margin-top:10px;">';
		echo '<a href="' . esc_url( admin_url( 'admin.php?page=logtrail' ) ) . '">';
		echo esc_html__( 'View all activity logs', 'logtrail' );
		echo '</a>';
		echo '</p>';
	}

	/**
	 * Check whether dashboard widget is enabled.
	 *
	 * @return bool
	 */
	private static function is_enabled(): bool {

		$settings = get_option( 'logtrail_general_settings', array() );

		if ( ! is_array( $settings ) ) {
			return true;
		}

		if ( ! array_key_exists( 'dashboardWidget', $settings ) ) {
			return true;
		}

		return (bool) $settings['dashboardWidget'];
	}
}
