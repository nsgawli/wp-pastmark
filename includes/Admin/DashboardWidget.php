<?php // phpcs:ignore WordPress.Files.FileName.NotHyphenatedLowercase
namespace Pastmark\Admin;

use Pastmark\Dashboard\DashboardLastViewed;
use Pastmark\Models\Pastmark_Logs;
use Pastmark\Utils\Helpers;

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
			'pastmark_latest_logs_widget',
			esc_html__( 'Latest Activity Logs', 'pastmark' ),
			array( __CLASS__, 'render_widget' )
		);
	}

	/**
	 * Render the latest logs dashboard widget.
	 *
	 * Highlights rows newer than this admin's stored "last viewed" timestamp
	 * with a "New" badge (PM-144), then records this visit via
	 * `DashboardLastViewed::mark_viewed()` *after* rendering — so the
	 * badges reflect what changed since the *previous* visit, and this
	 * visit's rows only stop being "new" starting from the next one.
	 *
	 * @return void
	 */
	public static function render_widget() {

		$user_id     = get_current_user_id();
		$last_viewed = DashboardLastViewed::get( $user_id );

		$model = new Pastmark_Logs();

		$logs = $model->get_logs(
			array(
				'number'  => 5,
				'orderby' => 'timestamp',
				'order'   => 'DESC',
			)
		);

		if ( empty( $logs ) ) {
			echo '<p>' . esc_html__( 'No activity logs found.', 'pastmark' ) . '</p>';

			DashboardLastViewed::mark_viewed( $user_id );

			return;
		}

		echo '<style>
			.pastmark-widget-row-new { background: #f0f6fc; }
			.pastmark-widget-new-badge {
				display: inline-block;
				margin-left: 6px;
				padding: 1px 6px;
				border-radius: 10px;
				background: #2271b1;
				color: #fff;
				font-size: 10px;
				font-weight: 600;
				text-transform: uppercase;
				letter-spacing: 0.02em;
				vertical-align: middle;
			}
		</style>';

		echo '<table class="widefat striped">';
		echo '<thead><tr>';
		echo '<th>' . esc_html__( 'Time', 'pastmark' ) . '</th>';
		echo '<th>' . esc_html__( 'User', 'pastmark' ) . '</th>';
		echo '<th>' . esc_html__( 'Event', 'pastmark' ) . '</th>';
		echo '<th>' . esc_html__( 'Message', 'pastmark' ) . '</th>';
		echo '</tr></thead>';
		echo '<tbody>';

		foreach ( $logs as $log ) {
			$user_label = esc_html__( 'System', 'pastmark' );

			if ( ! empty( $log->user_id ) ) {
				$user = get_userdata( (int) $log->user_id );

				if ( $user ) {
					$user_label = $user->display_name;
				}
			}

			$timestamp = Helpers::format_timestamp_for_display( (string) $log->timestamp );

			// A never-visited-before admin ('' last_viewed) sees everything
			// as new - there's no prior "seen" baseline to compare against.
			$is_new = ( '' === $last_viewed ) || ( (string) $log->timestamp > $last_viewed );

			echo '<tr' . ( $is_new ? ' class="pastmark-widget-row-new"' : '' ) . '>';
			echo '<td>' . esc_html( $timestamp );

			if ( $is_new ) {
				echo '<span class="pastmark-widget-new-badge">' . esc_html__( 'New', 'pastmark' ) . '</span>';
			}

			echo '</td>';
			echo '<td>' . esc_html( $user_label ) . '</td>';
			echo '<td>' . esc_html( $log->event_type ) . '</td>';
			echo '<td>' . esc_html( wp_trim_words( wp_strip_all_tags( (string) $log->message ), 14, '...' ) ) . '</td>';
			echo '</tr>';
		}

		echo '</tbody>';
		echo '</table>';

		echo '<p style="margin-top:10px;">';
		echo '<a href="' . esc_url( admin_url( 'admin.php?page=pastmark' ) ) . '">';
		echo esc_html__( 'View all activity logs', 'pastmark' );
		echo '</a>';
		echo '</p>';

		DashboardLastViewed::mark_viewed( $user_id );
	}

	/**
	 * Check whether dashboard widget is enabled.
	 *
	 * @return bool
	 */
	private static function is_enabled(): bool {

		$settings = get_option( 'pastmark_general_settings', array() );

		if ( ! is_array( $settings ) ) {
			return true;
		}

		if ( ! array_key_exists( 'dashboardWidget', $settings ) ) {
			return true;
		}

		return (bool) $settings['dashboardWidget'];
	}
}
