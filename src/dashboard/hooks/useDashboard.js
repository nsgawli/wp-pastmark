import { useEffect, useState } from 'react';
import { useAlerts } from '@framework/hooks/useAlerts';

import { getDashboard } from '../services/dashboardApi';

const useDashboard = () => {
	const [loading, setLoading] = useState(true);
	const [error, setError] = useState(null);

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

	const { addAlert } = useAlerts();

	const loadDashboard = async (selectedRange = range) => {
		setLoading(true);

		try {
			const response = await getDashboard(selectedRange);

			setDashboard(response.data);

			setRange(selectedRange);

			setError(null);
		} catch (err) {
			// A failed request must leave a distinct, visible signal behind -
			// previously this `catch` didn't exist at all, so `dashboard`
			// silently kept its prior value and the UI rendered exactly like
			// an empty-but-successful range, indistinguishable from a real
			// outage. `error` lets `DashboardLayout` show an actual error
			// state instead; the toast mirrors this codebase's existing
			// save-error pattern (see the settings pages) for consistency.
			console.error('Error loading dashboard:', err);

			setError(err);

			addAlert({
				id: Date.now(),
				type: 'error',
				title: 'Error',
				description: 'Unable to load dashboard data.',
			});
		} finally {
			setLoading(false);
		}
	};

	useEffect(() => {
		loadDashboard('30days');
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, []);

	return {
		loading,
		error,
		dashboard,
		loadDashboard,
		range,
		setRange,
	};
};

export default useDashboard;
