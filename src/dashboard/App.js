import React from 'react';

import { AdminPageHeader } from '@framework/components';

import ProductIcon from '@framework/icons/productIcon';

import DashboardLayout from './layout/DashboardLayout';
import useDashboard from './hooks/useDashboard';

const App = () => {
	const { loading, dashboard, loadDashboard, range, setRange } =
		useDashboard();

	return (
		<>
			<AdminPageHeader
				icon={<ProductIcon className="product-icon" />}
				title="LogTrail - User Activity Logger"
			/>

			<DashboardLayout
				loading={loading}
				dashboard={dashboard}
				range={range}
				loadDashboard={loadDashboard}
				setRange={setRange}
			/>
		</>
	);
};

export default App;
