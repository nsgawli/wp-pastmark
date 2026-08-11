import { useEffect, useState } from 'react';

import { getDashboard } from '../services/dashboardApi';

const useDashboard = () => {
	const [loading, setLoading] = useState(true);

	const [dashboard, setDashboard] = useState({
		summary: {},
		timeline: [],
		severity: [],
		top_categories: [],
		top_users: [],
		top_events: [],
		recent_alerts: [],
	});

	const [range, setRange] = useState('30days');

	const loadDashboard = async (selectedRange = range) => {
		setLoading(true);

		try {
			const response = await getDashboard(selectedRange);

			setDashboard(response.data);

			setRange(selectedRange);
		} finally {
			setLoading(false);
		}
	};

	useEffect(() => {
		loadDashboard('30days');
	}, []);

	return {
		loading,
		dashboard,
		loadDashboard,
		range,
		setRange,
	};
};

export default useDashboard;
