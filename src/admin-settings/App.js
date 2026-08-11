import React, { useEffect, useState } from 'react';
import {
	Route,
	Routes,
	Navigate,
	useNavigate,
	useLocation,
} from 'react-router-dom';
import {
	Flex,
	AdminPageHeader,
	SideMenu,
	AlertContainer,
} from '@framework/components';
import { __ } from '@wordpress/i18n';
import { applyFilters } from '@wordpress/hooks';

import ProductIcon from '@framework/icons/productIcon';

import { FiSettings, FiMinusCircle, FiTool, FiMail } from 'react-icons/fi';

import './index.css';

// pages
import GeneralSettings from './general-settings';
import EmailReports from './email-reports';
import ExcludeSettings from './exclude-settings';
import DataManagement from './data-management';

const defaultTabs = [
	{
		key: 'general-settings',
		path: '/general-settings',
		icon: <FiSettings />,
		label: __('General Settings', 'logtrail'),
		element: <GeneralSettings />,
	},
	{
		key: 'email-reports',
		path: '/email-reports',
		icon: <FiMail />,
		label: __('Email Reports', 'logtrail'),
		element: <EmailReports />,
	},
	{
		key: 'exclude-settings',
		path: '/exclude-settings',
		icon: <FiMinusCircle />,
		label: __('Exclude Settings', 'logtrail'),
		element: <ExcludeSettings />,
	},
	{
		key: 'data-management',
		path: '/data-management',
		icon: <FiTool />,
		label: __('Data Management', 'logtrail'),
		element: <DataManagement />,
	},
];

function App() {
	const navigate = useNavigate();
	const location = useLocation();
	const [current, setCurrent] = useState('general-settings');

	/**
	 * Extension point: add-ons (e.g. logtrail-pro) can inject additional
	 * settings tabs by filtering this list, each item shaped like
	 * defaultTabs. Evaluated at render time (not module load) so it picks
	 * up filters regardless of script load order.
	 */
	const menuItems = applyFilters('logtrail.settingsTabs', defaultTabs);

	useEffect(() => {
		if (location.pathname === '/') {
			navigate('/general-settings');
			return;
		}
		const path = location.pathname.replace(/\/$/, '').split('/');
		if (path.length > 1) {
			const currentRoute = menuItems.find((item) => item.key === path[1]);
			setCurrent(currentRoute ? currentRoute.key : 'general-settings');
		}
	}, [location]);

	const onClickMenuItem = (key) => {
		if (key === current) return;
		setCurrent(key);
		const currentRoute = menuItems.find((item) => item.key === key);
		navigate(currentRoute.path);
	};

	return (
		<Flex vertical>
			<AdminPageHeader
				icon={<ProductIcon className="product-icon" />}
				title="LogTrail - User Activity Logger"
			/>
			<Flex style={{ padding: '1.5rem' }} gap={25}>
				<SideMenu
					items={menuItems}
					current={current}
					onClick={onClickMenuItem}
					collapseKey="logtrailToggleMenuCollapse"
				/>
				<Routes>
					<Route path="/" element={<span />} />
					{menuItems.map((item) => (
						<Route
							key={item.key}
							path={`${item.path}/*`}
							element={item.element}
						/>
					))}
					<Route
						path="/advanced-settings/*"
						element={<Navigate to="/data-management" replace />}
					/>
				</Routes>
			</Flex>
			<AlertContainer />
		</Flex>
	);
}

export default App;
