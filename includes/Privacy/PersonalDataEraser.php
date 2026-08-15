<?php // phpcs:ignore WordPress.Files.FileName.NotHyphenatedLowercase
namespace Pastmark\Privacy;

use Pastmark\Models\Pastmark_Logs;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers Pastmark activity logs with the WordPress "Erase Personal Data" tool.
 *
 * Activity log entries are kept for security/audit purposes; erasure
 * anonymizes the IP address recorded on the requester's log entries
 * rather than deleting the entries outright.
 */
class PersonalDataEraser {

	/**
	 * Init hooks.
	 *
	 * @return void
	 */
	public static function init() {

		add_filter( 'wp_privacy_personal_data_erasers', array( __CLASS__, 'register_eraser' ) );
	}

	/**
	 * Register the eraser with WordPress.
	 *
	 * @param  array $erasers Registered erasers.
	 * @return array
	 */
	public static function register_eraser( $erasers ) {

		$erasers['pastmark'] = array(
			'eraser_friendly_name' => __( 'Pastmark User Activity Logs', 'pastmark' ),
			'callback'             => array( __CLASS__, 'erase' ),
		);

		return $erasers;
	}

	/**
	 * Anonymize the IP address on activity log entries for the requested account.
	 *
	 * @param  string $email_address Requester email address.
	 * @param  int    $page Page number.
	 * @return array
	 */
	public static function erase( $email_address, $page = 1 ) {

		$response = array(
			'items_removed'  => false,
			'items_retained' => true,
			'messages'       => array(),
			'done'           => true,
		);

		$user = get_user_by( 'email', $email_address );

		if ( ! $user ) {
			return $response;
		}

		// A single query anonymizes every matching row, so there is nothing
		// left to do on subsequent pages.
		if ( (int) $page > 1 ) {
			return $response;
		}

		$logs_model = new Pastmark_Logs();

		$anonymized = $logs_model->anonymize_logs_for_user( $user->ID );

		if ( $anonymized > 0 ) {
			$response['items_removed'] = true;
			$response['messages'][]    = __( 'Pastmark anonymized the IP addresses recorded on your account activity log entries. The activity entries themselves were retained for security and audit purposes.', 'pastmark' );
		}

		return $response;
	}
}
