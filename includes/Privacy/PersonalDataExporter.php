<?php // phpcs:ignore WordPress.Files.FileName.NotHyphenatedLowercase
namespace Pastmark\Privacy;

use Pastmark\Constants\Events;
use Pastmark\Constants\Actions;
use Pastmark\Models\Pastmark_Logs;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers Pastmark activity logs with the WordPress "Export Personal Data" tool.
 */
class PersonalDataExporter {

	/**
	 * Number of log entries exported per page.
	 */
	const ITEMS_PER_PAGE = 100;

	/**
	 * Init hooks.
	 *
	 * @return void
	 */
	public static function init() {

		add_filter( 'wp_privacy_personal_data_exporters', array( __CLASS__, 'register_exporter' ) );
	}

	/**
	 * Register the exporter with WordPress.
	 *
	 * @param  array $exporters Registered exporters.
	 * @return array
	 */
	public static function register_exporter( $exporters ) {

		$exporters['pastmark'] = array(
			'exporter_friendly_name' => __( 'Pastmark User Activity Logs', 'pastmark' ),
			'callback'               => array( __CLASS__, 'export' ),
		);

		return $exporters;
	}

	/**
	 * Export activity log entries recorded against the requested account.
	 *
	 * @param  string $email_address Requester email address.
	 * @param  int    $page Page number.
	 * @return array
	 */
	public static function export( $email_address, $page = 1 ) {

		$export_items = array();

		$user = get_user_by( 'email', $email_address );

		if ( ! $user ) {
			return array(
				'data' => $export_items,
				'done' => true,
			);
		}

		$page = (int) $page;
		$page = $page < 1 ? 1 : $page;

		$logs_model = new Pastmark_Logs();

		$logs = $logs_model->get_logs(
			array(
				'user_ids' => array( $user->ID ),
				'number'   => self::ITEMS_PER_PAGE,
				'offset'   => ( $page - 1 ) * self::ITEMS_PER_PAGE,
				'orderby'  => 'id',
				'order'    => 'ASC',
			)
		);

		foreach ( $logs as $log ) {

			$export_items[] = array(
				'group_id'          => 'pastmark-activity-logs',
				'group_label'       => __( 'Pastmark Activity Logs', 'pastmark' ),
				'group_description' => __( 'Activity recorded against your account by the Pastmark plugin.', 'pastmark' ),
				'item_id'           => 'pastmark-activity-log-' . $log->id,
				'data'              => array(
					array(
						'name'  => __( 'Date', 'pastmark' ),
						'value' => $log->timestamp,
					),
					array(
						'name'  => __( 'Event', 'pastmark' ),
						'value' => Events::resolve_label( (string) $log->event_type ),
					),
					array(
						'name'  => __( 'Action', 'pastmark' ),
						'value' => Actions::resolve_label( (string) $log->action ),
					),
					array(
						'name'  => __( 'Message', 'pastmark' ),
						'value' => $log->message,
					),
					array(
						'name'  => __( 'IP Address', 'pastmark' ),
						'value' => $log->ip_address,
					),
				),
			);
		}

		$done = count( $logs ) < self::ITEMS_PER_PAGE;

		return array(
			'data' => $export_items,
			'done' => $done,
		);
	}
}
