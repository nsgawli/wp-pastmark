import React from 'react';

import { applyFilters } from '@wordpress/hooks';

import { Content, Flex } from '@framework/components';

import DashboardToolbar from '../DashboardToolbar';

import SummaryCards from '../../widgets/SummaryCards';
import ActivityTimeline from '../../widgets/ActivityTimeline';
import SeverityDistribution from '../../widgets/SeverityDistribution';
import TopCategories from '../../widgets/TopCategories';
import MostActiveUsers from '../../widgets/MostActiveUsers';
import MostCommonEvents from '../../widgets/MostCommonEvents';
import RecentAlerts from '../../widgets/RecentAlerts';
import FailedLogins from '../../widgets/FailedLogins';

import './index.css';

const chunkPairs = (items) =>
	items.reduce((pairs, item, index) => {
		if (index % 2 === 0) {
			pairs.push([item]);
		} else {
			pairs[pairs.length - 1].push(item);
		}
		return pairs;
	}, []);

const DashboardLayout = ({ dashboard, loading, range, loadDashboard }) => {
	const filterContext = { dashboard, loading, range };

	/**
	 * Extension point: add-ons (e.g. pastmark-pro) can inject, reorder, or
	 * remove full-width dashboard widgets (summary/timeline row) by
	 * filtering this array.
	 */
	const topWidgets = applyFilters(
		'pastmark.dashboard.topWidgets',
		[
			{
				key: 'summary-cards',
				component: (
					<SummaryCards loading={loading} data={dashboard.summary} />
				),
			},
			{
				key: 'recent-alerts',
				component: <RecentAlerts data={dashboard.recent_alerts} />,
			},
			{
				key: 'activity-timeline',
				component: (
					<ActivityTimeline
						data={dashboard.timeline}
						loading={loading}
						range={range}
					/>
				),
			},
		],
		filterContext
	);

	/**
	 * Extension point: add-ons (e.g. pastmark-pro) can inject, reorder, or
	 * remove half-width chart widgets by filtering this array. Widgets are
	 * rendered two per row, in array order.
	 */
	const chartWidgets = applyFilters(
		'pastmark.dashboard.chartWidgets',
		[
			{
				key: 'severity-distribution',
				component: <SeverityDistribution data={dashboard.severity} />,
			},
			{
				key: 'top-categories',
				component: <TopCategories data={dashboard.top_categories} />,
			},
			{
				key: 'most-active-users',
				component: <MostActiveUsers data={dashboard.top_users} />,
			},
			{
				key: 'most-common-events',
				component: <MostCommonEvents data={dashboard.top_events} />,
			},
			{
				key: 'failed-logins',
				component: <FailedLogins data={dashboard.failed_logins} />,
			},
		],
		filterContext
	);

	return (
		<Content>
			<Flex className="wppm-dashboard-page" vertical gap={20}>
				<DashboardToolbar
					range={range}
					onRangeChange={loadDashboard}
					onRefresh={() => loadDashboard(range)}
				/>

				<Flex vertical gap={20}>
					{topWidgets.map(({ key, component }) => (
						<React.Fragment key={key}>{component}</React.Fragment>
					))}
				</Flex>

				{chunkPairs(chartWidgets).map((pair, index) => (
					<Flex key={index} gap={20} wrap align="stretch">
						{pair.map(({ key, component }) => (
							<div key={key} className="wppm-dashboard-chart-half">
								{component}
							</div>
						))}
					</Flex>
				))}
			</Flex>
		</Content>
	);
};

export default DashboardLayout;
