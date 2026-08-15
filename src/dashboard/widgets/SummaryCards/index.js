import React from 'react';

import { ScreenLoader } from '@framework/components';
import DashboardCard from '../../components/DashboardCard';

import './index.css';

const SummaryCards = ({ data = {}, loading = false }) => {
	if (loading) {
		return <ScreenLoader />;
	}

	return (
		<div className="wppm-dashboard-summary-grid">
			<DashboardCard
				title="Events Today"
				value={data.events_today || 0}
				tone="blue"
				variant="trend-up"
			/>

			<DashboardCard
				title="Events This Week"
				value={data.events_week || 0}
				tone="teal"
				variant="wave"
			/>

			<DashboardCard
				title="Events This Month"
				value={data.events_month || 0}
				tone="amber"
				variant="blocks"
			/>

			<DashboardCard
				title="Critical Events"
				value={data.critical_events || 0}
				tone="rose"
				variant="pulse"
			/>

			<DashboardCard
				title="Total Events"
				value={data.total_events || 0}
				tone="slate"
				variant="radial"
			/>
		</div>
	);
};

export default SummaryCards;
