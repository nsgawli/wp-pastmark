<?php // phpcs:ignore WordPress.Files.FileName.NotHyphenatedLowercase
namespace Pastmark\RestApi;

use WP_Error;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Base REST API controller.
 */
class BaseController {

	/**
	 * REST namespace.
	 *
	 * @var string
	 */
	protected $namespace = 'pastmark/v1';

	/**
	 * Permission callback.
	 *
	 * @return bool
	 */
	public function permission_callback() {

		return current_user_can( 'manage_options' );
	}

	/**
	 * Success response.
	 *
	 * @param mixed $data Response data.
	 * @return \WP_REST_Response
	 */
	protected function success_response( $data = array() ) {

		return rest_ensure_response(
			array(
				'success' => true,
				'data'    => $data,
			)
		);
	}

	/**
	 * Error response.
	 *
	 * @param string $code Error code.
	 * @param string $message Error message.
	 * @param int    $status Status code.
	 *
	 * @return WP_Error
	 */
	protected function error_response( $code, $message, $status = 400 ) {

		return new WP_Error(
			$code,
			$message,
			array(
				'status' => $status,
			)
		);
	}

	/**
	 * Parse comma-separated text values.
	 *
	 * @param  mixed $value Raw value.
	 * @return array
	 */
	protected function parse_csv_text( $value ) {

		$values = is_array( $value )
		? $value
		: explode( ',', (string) $value );

		$values = array_map(
			static function ( $item ) {
				return sanitize_text_field( trim( (string) $item ) );
			},
			$values
		);

		return array_values( array_unique( array_filter( $values ) ) );
	}

	/**
	 * Parse comma-separated integer values.
	 *
	 * @param  mixed $value Raw value.
	 * @return array
	 */
	protected function parse_csv_int( $value ) {

		$values = is_array( $value )
		? $value
		: explode( ',', (string) $value );

		$values = array_map( 'absint', $values );

		return array_values( array_unique( array_filter( $values ) ) );
	}

	/**
	 * Resolve date range preset/custom inputs to UTC datetime boundaries.
	 *
	 * @param  string $range Date range key.
	 * @param  string $from  Custom from date (Y-m-d).
	 * @param  string $to    Custom to date (Y-m-d).
	 * @return array
	 */
	protected function resolve_date_range( $range, $from, $to ) {

		$range = sanitize_text_field( (string) $range );
		$from  = sanitize_text_field( (string) $from );
		$to    = sanitize_text_field( (string) $to );

		$now = time();

		switch ( $range ) {
			case 'today':
				return array(
					'from' => gmdate( 'Y-m-d 00:00:00', $now ),
					'to'   => gmdate( 'Y-m-d 23:59:59', $now ),
				);

			case 'yesterday':
				$yesterday = strtotime( '-1 day', $now );
				return array(
					'from' => gmdate( 'Y-m-d 00:00:00', $yesterday ),
					'to'   => gmdate( 'Y-m-d 23:59:59', $yesterday ),
				);

			case 'last_7_days':
				$start = strtotime( '-6 days', $now );
				return array(
					'from' => gmdate( 'Y-m-d 00:00:00', $start ),
					'to'   => gmdate( 'Y-m-d 23:59:59', $now ),
				);

			case 'last_week':
				return array(
					'from' => gmdate( 'Y-m-d 00:00:00', strtotime( 'monday last week', $now ) ),
					'to'   => gmdate( 'Y-m-d 23:59:59', strtotime( 'sunday last week', $now ) ),
				);

			case 'last_month':
				return array(
					'from' => gmdate( 'Y-m-01 00:00:00', strtotime( 'first day of last month', $now ) ),
					'to'   => gmdate( 'Y-m-t 23:59:59', strtotime( 'last day of last month', $now ) ),
				);

			case 'last_30_days':
				$start = strtotime( '-29 days', $now );
				return array(
					'from' => gmdate( 'Y-m-d 00:00:00', $start ),
					'to'   => gmdate( 'Y-m-d 23:59:59', $now ),
				);

			case 'custom_range':
				if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $from ) && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $to ) ) {
					return array(
						'from' => $from . ' 00:00:00',
						'to'   => $to . ' 23:59:59',
					);
				}
				return array(
					'from' => '',
					'to'   => '',
				);

			case 'all':
			default:
				return array(
					'from' => '',
					'to'   => '',
				);
		}
	}
}
