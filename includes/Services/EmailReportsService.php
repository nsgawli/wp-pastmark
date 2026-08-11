<?php // phpcs:ignore WordPress.Files.FileName.NotHyphenatedLowercase
namespace LogTrail\Services;

use LogTrail\Installation\Settings\EmailReports as InstallationEmailReports;
use LogTrail\Models\LogTrail_Logs;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Email reports scheduler service.
 */
class EmailReportsService {

	/**
	 * Daily report cron hook.
	 */
	private const DAILY_CRON_HOOK = 'logtrail_send_daily_report_cron';

	/**
	 * Weekly report cron hook.
	 */
	private const WEEKLY_CRON_HOOK = 'logtrail_send_weekly_report_cron';

	/**
	 * Init hooks.
	 *
	 * @return void
	 */
	public static function init() {

		add_action( 'init', array( __CLASS__, 'maybe_schedule_daily_report' ) );
		add_action( 'init', array( __CLASS__, 'maybe_schedule_weekly_report' ) );

		add_action( self::DAILY_CRON_HOOK, array( __CLASS__, 'send_daily_report' ) );
		add_action( self::WEEKLY_CRON_HOOK, array( __CLASS__, 'send_weekly_report' ) );

		add_action(
			'update_option_logtrail_email_reports_settings',
			array( __CLASS__, 'on_settings_updated' ),
			10,
			2
		);
	}

	/**
	 * Handle settings updates and refresh scheduling.
	 *
	 * @param mixed $old_value Old option value.
	 * @param mixed $new_value New option value.
	 * @return void
	 */
	public static function on_settings_updated( $old_value, $new_value ) {

		self::clear_daily_schedule();
		self::clear_weekly_schedule();
		self::maybe_schedule_daily_report();
		self::maybe_schedule_weekly_report();
	}

	/**
	 * Ensure daily cron is scheduled at configured time.
	 *
	 * @return void
	 */
	public static function maybe_schedule_daily_report() {

		if ( ! self::is_daily_enabled() ) {
			self::clear_daily_schedule();
			return;
		}

		if ( wp_next_scheduled( self::DAILY_CRON_HOOK ) ) {
			return;
		}

		wp_schedule_single_event(
			self::get_next_daily_timestamp(),
			self::DAILY_CRON_HOOK
		);
	}

	/**
	 * Ensure weekly cron is scheduled at configured day and time.
	 *
	 * @return void
	 */
	public static function maybe_schedule_weekly_report() {

		if ( ! self::is_weekly_enabled() ) {
			self::clear_weekly_schedule();
			return;
		}

		if ( wp_next_scheduled( self::WEEKLY_CRON_HOOK ) ) {
			return;
		}

		wp_schedule_single_event(
			self::get_next_weekly_timestamp(),
			self::WEEKLY_CRON_HOOK
		);
	}

	/**
	 * Send daily report and schedule the next run.
	 *
	 * @return void
	 */
	public static function send_daily_report() {

		if ( ! self::is_daily_enabled() ) {
			self::clear_daily_schedule();
			return;
		}

		$settings   = self::get_settings();
		$recipients = self::get_daily_recipients( $settings );

		if ( ! empty( $recipients ) ) {
			self::send_report_email( 'daily', $recipients );
		}

		self::clear_daily_schedule();
		self::maybe_schedule_daily_report();
	}

	/**
	 * Send weekly report and schedule the next run.
	 *
	 * @return void
	 */
	public static function send_weekly_report() {

		if ( ! self::is_weekly_enabled() ) {
			self::clear_weekly_schedule();
			return;
		}

		$settings   = self::get_settings();
		$recipients = self::get_weekly_recipients( $settings );

		if ( ! empty( $recipients ) ) {
			self::send_report_email( 'weekly', $recipients );
		}

		self::clear_weekly_schedule();
		self::maybe_schedule_weekly_report();
	}

	/**
	 * Send report email using the shared template.
	 *
	 * @param string $report_type Report type.
	 * @param array  $recipients Recipient emails.
	 * @return bool
	 */
	public static function send_report_email( $report_type, array $recipients ) {

		if ( ! in_array( $report_type, array( 'daily', 'weekly' ), true ) ) {
			return false;
		}

		$recipients = self::sanitize_recipients( $recipients );

		if ( empty( $recipients ) ) {
			return false;
		}

		$template = self::build_report_template( $report_type );
		$content_type = ! empty( $template['content_type'] )
			? $template['content_type']
			: 'text/plain';

		return (bool) wp_mail(
			$recipients,
			$template['subject'],
			$template['body'],
			array( sprintf( 'Content-Type: %s; charset=UTF-8', $content_type ) )
		);
	}

	/**
	 * Build report email template.
	 *
	 * @param string $report_type Report type.
	 * @return array
	 */
	private static function build_report_template( $report_type ) {

		if ( 'weekly' === $report_type ) {
			return self::build_weekly_template();
		}

		return self::build_daily_template();
	}

	/**
	 * Build daily report email content.
	 *
	 * @return array
	 */
	private static function build_daily_template() {

		$window = self::get_report_window( 24 );

		$logs_model = new LogTrail_Logs();
		$summary    = $logs_model->get_dashboard_summary( $window['from_utc'], $window['to_utc'] );
		$top_events = $logs_model->get_top_events( $window['from_utc'], $window['to_utc'], 3 );

		$subject = sprintf(
			/* translators: %s: site name */
			__( 'LogTrail Daily Activity Report - %s', 'logtrail' ),
			wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES )
		);

		$total_events    = (int) $summary['total_events'];
		$critical_events = (int) $summary['critical_events'];
		$active_users    = (int) $summary['active_users'];
		$logs_url        = esc_url( admin_url( 'admin.php?page=logtrail' ) );

		$event_rows_html = '';

		if ( empty( $top_events ) ) {
			$event_rows_html = '<tr><td style="padding:10px 12px;color:#475467;border-bottom:1px solid #eaecf0;">' .
				esc_html__( 'No events recorded.', 'logtrail' ) .
			'</td><td style="padding:10px 12px;color:#475467;border-bottom:1px solid #eaecf0;text-align:right;">0</td></tr>';
		} else {
			foreach ( $top_events as $event ) {
				$event_rows_html .= '<tr>' .
					'<td style="padding:10px 12px;color:#344054;border-bottom:1px solid #eaecf0;">' . esc_html( $event['label'] ) . '</td>' .
					'<td style="padding:10px 12px;color:#344054;border-bottom:1px solid #eaecf0;text-align:right;font-weight:600;">' . (int) $event['total'] . '</td>' .
				'</tr>';
			}
		}

		$body_html  = '<!doctype html><html><head><meta charset="UTF-8"></head><body style="margin:0;padding:0;background:#f5f7fb;font-family:-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,Helvetica,Arial,sans-serif;">';
		$body_html .= '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f5f7fb;padding:24px 12px;"><tr><td align="center">';
		$body_html .= '<table role="presentation" width="680" cellspacing="0" cellpadding="0" style="max-width:680px;background:#ffffff;border:1px solid #eaecf0;border-radius:14px;overflow:hidden;">';
		$body_html .= '<tr><td style="padding:24px 28px;background:linear-gradient(120deg,#0f172a,#1d4ed8);color:#ffffff;">';
		$body_html .= '<div style="font-size:13px;opacity:.9;letter-spacing:.3px;">' . esc_html__( 'LogTrail Daily Activity Report', 'logtrail' ) . '</div>';
		$body_html .= '<h1 style="margin:8px 0 4px;font-size:24px;line-height:1.25;">' . esc_html( wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ) ) . '</h1>';
		$body_html .= '<div style="font-size:13px;opacity:.92;">' .
			// translators: %1$s: from timestamp, %2$s: to timestamp.
			esc_html( sprintf( __( 'Window: %1$s to %2$s (UTC)', 'logtrail' ), $window['from_utc'], $window['to_utc'] ) ) .
		'</div>';
		$body_html .= '</td></tr>';
		$body_html .= '<tr><td style="padding:24px 28px 8px;color:#344054;font-size:14px;line-height:1.6;">';
		$body_html .= '<p style="margin:0 0 12px;">' . esc_html__( 'Hello,', 'logtrail' ) . '</p>';
		$body_html .= '<p style="margin:0;">' . esc_html__( 'Here is your organized daily operations snapshot for the last 24 hours.', 'logtrail' ) . '</p>';
		$body_html .= '</td></tr>';
		$body_html .= '<tr><td style="padding:8px 28px 20px;">';
		$body_html .= '<table role="presentation" width="100%" cellspacing="0" cellpadding="0"><tr>';
		$body_html .= '<td style="padding:0 8px 0 0;"><div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;padding:12px;"><div style="font-size:12px;color:#475467;">' . esc_html__( 'Total Events', 'logtrail' ) . '</div><div style="margin-top:6px;font-size:24px;font-weight:700;color:#0f172a;">' . $total_events . '</div></div></td>';
		$body_html .= '<td style="padding:0 8px;"><div style="background:#fff7ed;border:1px solid #fed7aa;border-radius:10px;padding:12px;"><div style="font-size:12px;color:#9a3412;">' . esc_html__( 'Critical Events', 'logtrail' ) . '</div><div style="margin-top:6px;font-size:24px;font-weight:700;color:#9a3412;">' . $critical_events . '</div></div></td>';
		$body_html .= '<td style="padding:0 0 0 8px;"><div style="background:#ecfdf3;border:1px solid #abefc6;border-radius:10px;padding:12px;"><div style="font-size:12px;color:#067647;">' . esc_html__( 'Active Users', 'logtrail' ) . '</div><div style="margin-top:6px;font-size:24px;font-weight:700;color:#067647;">' . $active_users . '</div></div></td>';
		$body_html .= '</tr></table>';
		$body_html .= '</td></tr>';
		$body_html .= '<tr><td style="padding:0 28px 18px;">';
		$body_html .= '<h3 style="margin:0 0 10px;color:#111827;font-size:16px;">' . esc_html__( 'Top Event Categories Today', 'logtrail' ) . '</h3>';
		$body_html .= '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border:1px solid #eaecf0;border-radius:10px;overflow:hidden;background:#ffffff;">';
		$body_html .= '<tr><th align="left" style="padding:10px 12px;font-size:12px;color:#475467;background:#f9fafb;border-bottom:1px solid #eaecf0;">' . esc_html__( 'Category', 'logtrail' ) . '</th><th align="right" style="padding:10px 12px;font-size:12px;color:#475467;background:#f9fafb;border-bottom:1px solid #eaecf0;">' . esc_html__( 'Events', 'logtrail' ) . '</th></tr>';
		$body_html .= $event_rows_html;
		$body_html .= '</table>';
		$body_html .= '</td></tr>';
		$body_html .= '<tr><td style="padding:0 28px 24px;">';
		$body_html .= '<div style="padding:12px 14px;border-radius:10px;background:#eff8ff;border:1px solid #b2ddff;color:#1849a9;font-size:13px;line-height:1.5;">';
		$body_html .= esc_html__( 'Recommended action: review any critical events immediately and investigate unusual category spikes.', 'logtrail' );
		$body_html .= '</div>';
		$body_html .= '<div style="padding-top:14px;">';
		$body_html .= '<a href="' . $logs_url . '" style="display:inline-block;background:#1d4ed8;color:#ffffff;text-decoration:none;padding:10px 14px;border-radius:8px;font-size:13px;font-weight:600;">' . esc_html__( 'View Activity Logs', 'logtrail' ) . '</a>';
		$body_html .= '</div>';
		$body_html .= '</td></tr>';
		$body_html .= '<tr><td style="padding:14px 28px;border-top:1px solid #eaecf0;background:#fcfcfd;color:#667085;font-size:12px;">';
		$body_html .= esc_html__( 'This email was generated automatically by LogTrail.', 'logtrail' );
		$body_html .= '</td></tr>';
		$body_html .= '</table></td></tr></table></body></html>';

		return array(
			'subject'      => $subject,
			'body'         => $body_html,
			'content_type' => 'text/html',
		);
	}

	/**
	 * Build weekly report email content.
	 *
	 * @return array
	 */
	private static function build_weekly_template() {

		$window = self::get_report_window( 24 * 7 );

		$logs_model = new LogTrail_Logs();
		$summary    = $logs_model->get_dashboard_summary( $window['from_utc'], $window['to_utc'] );
		$top_events = $logs_model->get_top_events( $window['from_utc'], $window['to_utc'], 5 );
		$timeline   = $logs_model->get_activity_timeline( $window['from_utc'], $window['to_utc'] );

		$average_per_day = round( (int) $summary['total_events'] / 7, 1 );

		$subject = sprintf(
			/* translators: %s: site name */
			__( 'LogTrail Weekly Activity Digest - %s', 'logtrail' ),
			wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES )
		);

		$total_events    = (int) $summary['total_events'];
		$critical_events = (int) $summary['critical_events'];
		$active_users    = (int) $summary['active_users'];
		$logs_url        = esc_url( admin_url( 'admin.php?page=logtrail' ) );

		$event_rows_html = '';
		$timeline_rows_html = '';

		if ( empty( $top_events ) ) {
			$event_rows_html = '<tr><td style="padding:10px 12px;color:#475467;border-bottom:1px solid #eaecf0;">' .
				esc_html__( 'No events recorded.', 'logtrail' ) .
			'</td><td style="padding:10px 12px;color:#475467;border-bottom:1px solid #eaecf0;text-align:right;">0</td></tr>';
		} else {
			foreach ( $top_events as $event ) {
				$event_rows_html .= '<tr>' .
					'<td style="padding:10px 12px;color:#344054;border-bottom:1px solid #eaecf0;">' . esc_html( $event['label'] ) . '</td>' .
					'<td style="padding:10px 12px;color:#344054;border-bottom:1px solid #eaecf0;text-align:right;font-weight:600;">' . (int) $event['total'] . '</td>' .
				'</tr>';
			}
		}

		if ( empty( $timeline ) ) {
			$timeline_rows_html = '<tr><td style="padding:10px 12px;color:#475467;border-bottom:1px solid #eaecf0;">' .
				esc_html__( 'No events recorded.', 'logtrail' ) .
			'</td><td style="padding:10px 12px;color:#475467;border-bottom:1px solid #eaecf0;text-align:right;">0</td></tr>';
		} else {
			foreach ( $timeline as $row ) {
				$timeline_rows_html .= '<tr>' .
					'<td style="padding:10px 12px;color:#344054;border-bottom:1px solid #eaecf0;">' . esc_html( $row['label'] ) . '</td>' .
					'<td style="padding:10px 12px;color:#344054;border-bottom:1px solid #eaecf0;text-align:right;font-weight:600;">' . (int) $row['total'] . '</td>' .
				'</tr>';
			}
		}

		$body_html  = '<!doctype html><html><head><meta charset="UTF-8"></head><body style="margin:0;padding:0;background:#f5f7fb;font-family:-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,Helvetica,Arial,sans-serif;">';
		$body_html .= '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f5f7fb;padding:24px 12px;"><tr><td align="center">';
		$body_html .= '<table role="presentation" width="680" cellspacing="0" cellpadding="0" style="max-width:680px;background:#ffffff;border:1px solid #eaecf0;border-radius:14px;overflow:hidden;">';
		$body_html .= '<tr><td style="padding:24px 28px;background:linear-gradient(120deg,#1f2937,#7c3aed);color:#ffffff;">';
		$body_html .= '<div style="font-size:13px;opacity:.9;letter-spacing:.3px;">' . esc_html__( 'LogTrail Weekly Activity Digest', 'logtrail' ) . '</div>';
		$body_html .= '<h1 style="margin:8px 0 4px;font-size:24px;line-height:1.25;">' . esc_html( wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ) ) . '</h1>';
		$body_html .= '<div style="font-size:13px;opacity:.92;">' .
			// translators: %1$s: from timestamp, %2$s: to timestamp.
			esc_html( sprintf( __( 'Window: %1$s to %2$s (UTC)', 'logtrail' ), $window['from_utc'], $window['to_utc'] ) ) .
		'</div>';
		$body_html .= '</td></tr>';
		$body_html .= '<tr><td style="padding:24px 28px 8px;color:#344054;font-size:14px;line-height:1.6;">';
		$body_html .= '<p style="margin:0 0 12px;">' . esc_html__( 'Hello,', 'logtrail' ) . '</p>';
		$body_html .= '<p style="margin:0;">' . esc_html__( 'Here is your organized weekly performance digest for the last 7 days.', 'logtrail' ) . '</p>';
		$body_html .= '</td></tr>';
		$body_html .= '<tr><td style="padding:8px 28px 20px;">';
		$body_html .= '<table role="presentation" width="100%" cellspacing="0" cellpadding="0"><tr>';
		$body_html .= '<td style="padding:0 8px 0 0;"><div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;padding:12px;"><div style="font-size:12px;color:#475467;">' . esc_html__( 'Total Events (7d)', 'logtrail' ) . '</div><div style="margin-top:6px;font-size:24px;font-weight:700;color:#0f172a;">' . $total_events . '</div></div></td>';
		$body_html .= '<td style="padding:0 8px;"><div style="background:#faf5ff;border:1px solid #e9d5ff;border-radius:10px;padding:12px;"><div style="font-size:12px;color:#6b21a8;">' . esc_html__( 'Avg / Day', 'logtrail' ) . '</div><div style="margin-top:6px;font-size:24px;font-weight:700;color:#6b21a8;">' . esc_html( (string) $average_per_day ) . '</div></div></td>';
		$body_html .= '<td style="padding:0 0 0 8px;"><div style="background:#fff1f2;border:1px solid #fecdd3;border-radius:10px;padding:12px;"><div style="font-size:12px;color:#be123c;">' . esc_html__( 'Critical Events', 'logtrail' ) . '</div><div style="margin-top:6px;font-size:24px;font-weight:700;color:#be123c;">' . $critical_events . '</div></div></td>';
		$body_html .= '</tr></table>';
		$body_html .= '</td></tr>';
		$body_html .= '<tr><td style="padding:0 28px 10px;">';
		$body_html .= '<div style="background:#ecfeff;border:1px solid #a5f3fc;border-radius:10px;padding:10px 12px;color:#155e75;font-size:13px;">';
		// translators: %d: number of unique active users.
		$body_html .= sprintf( esc_html__( 'Unique active users this week: %d', 'logtrail' ), $active_users );
		$body_html .= '</div>';
		$body_html .= '</td></tr>';
		$body_html .= '<tr><td style="padding:8px 28px 18px;">';
		$body_html .= '<h3 style="margin:0 0 10px;color:#111827;font-size:16px;">' . esc_html__( 'Top Event Categories This Week', 'logtrail' ) . '</h3>';
		$body_html .= '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border:1px solid #eaecf0;border-radius:10px;overflow:hidden;background:#ffffff;">';
		$body_html .= '<tr><th align="left" style="padding:10px 12px;font-size:12px;color:#475467;background:#f9fafb;border-bottom:1px solid #eaecf0;">' . esc_html__( 'Category', 'logtrail' ) . '</th><th align="right" style="padding:10px 12px;font-size:12px;color:#475467;background:#f9fafb;border-bottom:1px solid #eaecf0;">' . esc_html__( 'Events', 'logtrail' ) . '</th></tr>';
		$body_html .= $event_rows_html;
		$body_html .= '</table>';
		$body_html .= '</td></tr>';
		$body_html .= '<tr><td style="padding:0 28px 18px;">';
		$body_html .= '<h3 style="margin:0 0 10px;color:#111827;font-size:16px;">' . esc_html__( 'Event Count By Day', 'logtrail' ) . '</h3>';
		$body_html .= '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border:1px solid #eaecf0;border-radius:10px;overflow:hidden;background:#ffffff;">';
		$body_html .= '<tr><th align="left" style="padding:10px 12px;font-size:12px;color:#475467;background:#f9fafb;border-bottom:1px solid #eaecf0;">' . esc_html__( 'Date (UTC)', 'logtrail' ) . '</th><th align="right" style="padding:10px 12px;font-size:12px;color:#475467;background:#f9fafb;border-bottom:1px solid #eaecf0;">' . esc_html__( 'Events', 'logtrail' ) . '</th></tr>';
		$body_html .= $timeline_rows_html;
		$body_html .= '</table>';
		$body_html .= '</td></tr>';
		$body_html .= '<tr><td style="padding:0 28px 24px;">';
		$body_html .= '<div style="padding:12px 14px;border-radius:10px;background:#f5f3ff;border:1px solid #ddd6fe;color:#5b21b6;font-size:13px;line-height:1.5;">';
		$body_html .= esc_html__( 'Recommended action: review trend spikes, compare weekly averages, and fine-tune event settings where noise is high.', 'logtrail' );
		$body_html .= '</div>';
		$body_html .= '<div style="padding-top:14px;">';
		$body_html .= '<a href="' . $logs_url . '" style="display:inline-block;background:#5b21b6;color:#ffffff;text-decoration:none;padding:10px 14px;border-radius:8px;font-size:13px;font-weight:600;">' . esc_html__( 'View Activity Logs', 'logtrail' ) . '</a>';
		$body_html .= '</div>';
		$body_html .= '</td></tr>';
		$body_html .= '<tr><td style="padding:14px 28px;border-top:1px solid #eaecf0;background:#fcfcfd;color:#667085;font-size:12px;">';
		$body_html .= esc_html__( 'This email was generated automatically by LogTrail.', 'logtrail' );
		$body_html .= '</td></tr>';
		$body_html .= '</table></td></tr></table></body></html>';

		return array(
			'subject'      => $subject,
			'body'         => $body_html,
			'content_type' => 'text/html',
		);
	}

	/**
	 * Get report summary for the selected period.
	 *
	 * @param int $period_hours Period in hours.
	 * @return array
	 */
	private static function get_report_window( $period_hours ) {

		$now_utc  = new \DateTimeImmutable( 'now', new \DateTimeZone( 'UTC' ) );
		$from_utc = $now_utc->modify( sprintf( '-%d hours', max( 1, (int) $period_hours ) ) );

		return array(
			'from_utc' => $from_utc->format( 'Y-m-d H:i:s' ),
			'to_utc'   => $now_utc->format( 'Y-m-d H:i:s' ),
		);
	}

	/**
	 * Get next timestamp for configured daily send time in site timezone.
	 *
	 * @return int
	 */
	private static function get_next_daily_timestamp() {

		$settings = self::get_settings();
		$time     = self::sanitize_time_hhmm( $settings['dailySendTime'] ?? '20:00' );
		$parts    = explode( ':', $time );

		$hour   = (int) $parts[0];
		$minute = (int) $parts[1];

		$timezone = wp_timezone();
		$now      = new \DateTimeImmutable( 'now', $timezone );

		$next = $now->setTime( $hour, $minute, 0 );

		if ( $next <= $now ) {
			$next = $next->modify( '+1 day' );
		}

		return $next->getTimestamp();
	}

	/**
	 * Get next timestamp for configured weekly day and time in site timezone.
	 *
	 * @return int
	 */
	private static function get_next_weekly_timestamp() {

		$settings = self::get_settings();

		$day  = self::sanitize_weekday( $settings['weeklySendDay'] ?? 'friday' );
		$time = self::sanitize_time_hhmm( $settings['weeklySendTime'] ?? '21:00' );

		$day_to_num = array(
			'monday'    => 1,
			'tuesday'   => 2,
			'wednesday' => 3,
			'thursday'  => 4,
			'friday'    => 5,
			'saturday'  => 6,
			'sunday'    => 7,
		);

		$target_day_num = $day_to_num[ $day ];

		$parts  = explode( ':', $time );
		$hour   = (int) $parts[0];
		$minute = (int) $parts[1];

		$timezone = wp_timezone();
		$now      = new \DateTimeImmutable( 'now', $timezone );

		$current_day_num = (int) $now->format( 'N' );
		$days_until      = $target_day_num - $current_day_num;

		$next = $now->setTime( $hour, $minute, 0 );

		if ( $days_until < 0 ) {
			$days_until += 7;
		}

		if ( 0 === $days_until && $next <= $now ) {
			$days_until = 7;
		}

		if ( $days_until > 0 ) {
			$next = $next->modify( sprintf( '+%d days', $days_until ) );
		}

		return $next->getTimestamp();
	}

	/**
	 * Check if daily report is enabled.
	 *
	 * @return bool
	 */
	private static function is_daily_enabled() {

		$settings = self::get_settings();

		return ! empty( $settings['enableDailyReport'] );
	}

	/**
	 * Check if weekly report is enabled.
	 *
	 * @return bool
	 */
	private static function is_weekly_enabled() {

		$settings = self::get_settings();

		return ! empty( $settings['enableWeeklyReport'] );
	}

	/**
	 * Resolve daily recipients, falling back to admin email.
	 *
	 * @param array $settings Email report settings.
	 * @return array
	 */
	private static function get_daily_recipients( array $settings ) {

		$recipients = self::sanitize_recipients( $settings['dailyRecipients'] ?? array() );

		if ( ! empty( $recipients ) ) {
			return $recipients;
		}

		$admin_email = get_option( 'admin_email' );
		return is_email( $admin_email ) ? array( $admin_email ) : array();
	}

	/**
	 * Resolve weekly recipients, falling back to admin email.
	 *
	 * @param array $settings Email report settings.
	 * @return array
	 */
	private static function get_weekly_recipients( array $settings ) {

		$recipients = self::sanitize_recipients( $settings['weeklyRecipients'] ?? array() );

		if ( ! empty( $recipients ) ) {
			return $recipients;
		}

		$admin_email = get_option( 'admin_email' );
		return is_email( $admin_email ) ? array( $admin_email ) : array();
	}

	/**
	 * Get normalized settings with defaults.
	 *
	 * @return array
	 */
	private static function get_settings() {

		$settings = get_option( 'logtrail_email_reports_settings', array() );

		if ( ! is_array( $settings ) ) {
			$settings = array();
		}

		return wp_parse_args(
			$settings,
			InstallationEmailReports::get_default_settings()
		);
	}

	/**
	 * Sanitize list of recipient emails.
	 *
	 * @param mixed $emails Raw value.
	 * @return array
	 */
	private static function sanitize_recipients( $emails ) {

		if ( ! is_array( $emails ) ) {
			return array();
		}

		$sanitized = array();

		foreach ( $emails as $email ) {
			$email = sanitize_email( $email );
			if ( ! empty( $email ) && is_email( $email ) ) {
				$sanitized[] = $email;
			}
		}

		return array_values( array_unique( $sanitized ) );
	}

	/**
	 * Sanitize HH:mm time string.
	 *
	 * @param mixed $value Raw value.
	 * @return string
	 */
	private static function sanitize_time_hhmm( $value ) {

		$value = is_string( $value )
			? trim( sanitize_text_field( $value ) )
			: '';

		if ( preg_match( '/^(?:[01][0-9]|2[0-3]):[0-5][0-9]$/', $value ) ) {
			return $value;
		}

		return '20:00';
	}

	/**
	 * Sanitize weekday key.
	 *
	 * @param mixed $value Raw value.
	 * @return string
	 */
	private static function sanitize_weekday( $value ) {

		$value = is_string( $value )
			? strtolower( trim( sanitize_text_field( $value ) ) )
			: '';

		$days = array(
			'monday',
			'tuesday',
			'wednesday',
			'thursday',
			'friday',
			'saturday',
			'sunday',
		);

		return in_array( $value, $days, true )
			? $value
			: 'friday';
	}

	/**
	 * Unschedule all pending daily report events.
	 *
	 * @return void
	 */
	private static function clear_daily_schedule() {

		$timestamp = wp_next_scheduled( self::DAILY_CRON_HOOK );

		while ( $timestamp ) {
			wp_unschedule_event( $timestamp, self::DAILY_CRON_HOOK );
			$timestamp = wp_next_scheduled( self::DAILY_CRON_HOOK );
		}
	}

	/**
	 * Unschedule all pending weekly report events.
	 *
	 * @return void
	 */
	private static function clear_weekly_schedule() {

		$timestamp = wp_next_scheduled( self::WEEKLY_CRON_HOOK );

		while ( $timestamp ) {
			wp_unschedule_event( $timestamp, self::WEEKLY_CRON_HOOK );
			$timestamp = wp_next_scheduled( self::WEEKLY_CRON_HOOK );
		}
	}
}
