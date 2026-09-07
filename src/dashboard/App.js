import React from 'react';

import { AdminPageHeader, AlertContainer } from '@framework/components';

import ProductIcon from '@framework/icons/productIcon';

import DashboardLayout from './layout/DashboardLayout';
import useDashboard from './hooks/useDashboard';

const App = () => {
	const { loading, error, dashboard, loadDashboard, range, setRange } =
		useDashboard();

	return (
		<>
			<AdminPageHeader
				icon={<ProductIcon className="product-icon" />}
				title="Pastmark - User Activity Logs"
			/>

			{/*
				`AlertsProvider` already wraps this app (see index.js), but
				nothing rendered the queue it manages - `addAlert()` calls
				(e.g. `useDashboard.js`'s load-error toast) had nowhere to
				show up. Mirrors `admin-settings/App.js`'s existing usage.
			*/}
			<AlertContainer />

			<DashboardLayout
				loading={loading}
				error={error}
				dashboard={dashboard}
				range={range}
				loadDashboard={loadDashboard}
				setRange={setRange}
			/>
		</>
	);
};

export default App;
