<?php // phpcs:ignore WordPress.Files.FileName.NotHyphenatedLowercase

namespace LogTrail\RestApi\Settings;

use LogTrail\Installation\Settings\EmailReports as InstallationEmailReports;
use LogTrail\RestApi\BaseController;
use LogTrail\Services\EmailReportsService;
use WP_REST_Request;

defined( 'ABSPATH' ) || exit;

/**
 * Email report settings REST controller.
 */
class EmailReports extends BaseController {

	/**
	 * Init.
	 *
	 * @return void
	 */
	public static function init() {

		$instance = new self();

		add_action(
			'rest_api_init',
			array( $instance, 'register_routes' )
		);
	}

	/**
	 * Register routes.
	 *
	 * @return void
	 */
	public function register_routes() {

		register_rest_route(
			$this->namespace,
			'/settings/email-reports',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'get_settings' ),
					'permission_callback' => array( $this, 'permission_callback' ),
				),
				array(
					'methods'             => 'PUT',
					'callback'            => array( $this, 'update_settings' ),
					'permission_callback' => array( $this, 'permission_callback' ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/settings/email-reports/defaults',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_default_settings' ),
				'permission_callback' => array( $this, 'permission_callback' ),
			)
		);

		register_rest_route(
			$this->namespace,
			'/settings/email-reports/send-test',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'send_test_email' ),
				'permission_callback' => array( $this, 'permission_callback' ),
			)
		);
	}

	/**
	 * Get settings.
	 *
	 * @return \WP_REST_Response
	 */
	public function get_settings() {

		$settings = get_option( 'logtrail_email_reports_settings', array() );

		if ( empty( $settings ) || ! is_array( $settings ) ) {
			$settings = InstallationEmailReports::get_default_settings();
		}

		$settings = wp_parse_args(
			$settings,
			InstallationEmailReports::get_default_settings()
		);

		return $this->success_response( $settings );
	}

	/**
	 * Update settings.
	 *
	 * @param WP_REST_Request $request Request object.
	 *
	 * @return \WP_REST_Response
	 */
	public function update_settings( WP_REST_Request $request ) {

		$settings = array(
			'enableDailyReport'  => (bool) $request->get_param( 'enableDailyReport' ),
			'dailySendTime'      => $this->sanitize_time_hhmm( $request->get_param( 'dailySendTime' ) ),
			'dailyRecipients'    => $this->sanitize_recipients(
				$request->get_param( 'dailyRecipients' )
			),
			'enableWeeklyReport' => (bool) $request->get_param( 'enableWeeklyReport' ),
			'weeklySendDay'      => $this->sanitize_weekday( $request->get_param( 'weeklySendDay' ) ),
			'weeklySendTime'     => $this->sanitize_time_hhmm( $request->get_param( 'weeklySendTime' ) ),
			'weeklyRecipients'   => $this->sanitize_recipients(
				$request->get_param( 'weeklyRecipients' )
			),
		);

		update_option( 'logtrail_email_reports_settings', $settings );

		return $this->success_response( $settings );
	}

	/**
	 * Send a test report email.
	 *
	 * @param WP_REST_Request $request Request object.
	 *
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function send_test_email( WP_REST_Request $request ) {

		$report_type = sanitize_text_field( $request->get_param( 'reportType' ) );
		$recipients  = $this->sanitize_recipients( $request->get_param( 'recipients' ) );

		if ( ! in_array( $report_type, array( 'daily', 'weekly' ), true ) ) {
			return $this->error_response(
				'invalid_report_type',
				__( 'Invalid report type.', 'logtrail' )
			);
		}

		if ( empty( $recipients ) ) {
			return $this->error_response(
				'missing_recipients',
				__( 'Please add at least one recipient email.', 'logtrail' )
			);
		}

		$sent = EmailReportsService::send_report_email( $report_type, $recipients );

		if ( ! $sent ) {
			return $this->error_response(
				'email_send_failed',
				__( 'Unable to send test email. Please verify your mail configuration.', 'logtrail' )
			);
		}

		return $this->success_response(
			array(
				'reportType' => $report_type,
				'recipients' => $recipients,
			)
		);
	}

	/**
	 * Get default settings.
	 *
	 * @return \WP_REST_Response
	 */
	public function get_default_settings() {

		return $this->success_response(
			InstallationEmailReports::get_default_settings()
		);
	}

	/**
	 * Sanitize recipients list.
	 *
	 * @param mixed $emails Recipient values.
	 * @return array
	 */
	private function sanitize_recipients( $emails ) {

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
	private function sanitize_time_hhmm( $value ) {

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
	private function sanitize_weekday( $value ) {

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
}
