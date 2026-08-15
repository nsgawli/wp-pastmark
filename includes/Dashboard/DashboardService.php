<?php // phpcs:ignore WordPress.Files.FileName.NotHyphenatedLowercase
/**
 * Dashboard Service.
 *
 * @package Pastmark
 */

namespace Pastmark\Dashboard;

use Pastmark\Models\Pastmark_Logs;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Dashboard service.
 */
class DashboardService {

	/**
	 * Logs model.
	 *
	 * @var Pastmark_Logs
	 */
	private Pastmark_Logs $logs_model;

	/**
	 * Constructor.
	 */
	public function __construct() {

		$this->logs_model = new Pastmark_Logs();
	}

	/**
	 * Get dashboard data.
	 *
	 * @param string $range Date range.
	 * @return array
	 */
	public function get_dashboard(
		string $range = '30days'
	): array {

		$today = time();

		$today_start = gmdate(
			'Y-m-d 00:00:00',
			$today
		);

		$today_end = gmdate(
			'Y-m-d 23:59:59',
			$today
		);

		$week_start = gmdate(
			'Y-m-d 00:00:00',
			strtotime( '-6 days', $today )
		);

		$month_start = gmdate(
			'Y-m-d 00:00:00',
			strtotime( '-29 days', $today )
		);

		/*
		|--------------------------------------------------------------------------
		| Global KPI Cards
		|--------------------------------------------------------------------------
		*/

		$total = $this->logs_model->count_logs();

		$today_summary = $this->logs_model->get_dashboard_summary(
			$today_start,
			$today_end
		);

		$week_summary = $this->logs_model->get_dashboard_summary(
			$week_start,
			$today_end
		);

		$month_summary = $this->logs_model->get_dashboard_summary(
			$month_start,
			$today_end
		);

		/*
		|--------------------------------------------------------------------------
		| Dashboard Filter Range
		|--------------------------------------------------------------------------
		*/

		switch ( $range ) {

			case 'today':
				$start = $today_start;
				break;

			case '7days':
				$start = gmdate(
					'Y-m-d 00:00:00',
					strtotime( '-6 days', $today )
				);
				break;

			case '30days':
				$start = gmdate(
					'Y-m-d 00:00:00',
					strtotime( '-29 days', $today )
				);
				break;

			case '90days':
				$start = gmdate(
					'Y-m-d 00:00:00',
					strtotime( '-89 days', $today )
				);
				break;

			default:
				$start = gmdate(
					'Y-m-d 00:00:00',
					strtotime( '-29 days', $today )
				);

		}

		$end = $today_end;

		return array(

			/*
			|--------------------------------------------------------------------------
			| Global Cards
			|--------------------------------------------------------------------------
			*/

			'summary'        => array(

				'events_today'    => $today_summary['total_events'],

				'events_week'     => $week_summary['total_events'],

				'events_month'    => $month_summary['total_events'],

				'critical_events' => $month_summary['critical_events'],

				'active_users'    => $month_summary['active_users'],

				'total_events'    => $total,

			),

			/*
			|--------------------------------------------------------------------------
			| Filtered Widgets
			|--------------------------------------------------------------------------
			*/

			'timeline'       => $this->logs_model->get_activity_timeline(
				$start,
				$end
			),

			'severity'       => $this->logs_model->get_severity_distribution(
				$start,
				$end
			),

			'top_categories' => $this->logs_model->get_top_categories(
				$start,
				$end
			),

			'top_users'      => $this->logs_model->get_top_users(
				$start,
				$end
			),

			'top_events'     => $this->logs_model->get_top_events(
				$start,
				$end
			),

			'failed_logins'  => $this->logs_model->get_top_failed_login_ips(
				$start,
				$end
			),

			'recent_alerts'  => $this->logs_model->get_recent_high_severity_events(),

		);
	}
}
