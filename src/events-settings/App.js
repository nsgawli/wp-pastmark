import React, { useEffect, useState } from 'react';

import {
	Flex,
	Content,
	ScreenLoader,
	AdminPageHeader,
} from '@framework/components';

import ProductIcon from '@framework/icons/productIcon';

import { useAlerts } from '@framework/hooks/useAlerts';

import useEvents from './hooks/useEvents';

import SaveBar from './components/SaveBar';
import EventFilters from './components/EventFilters';
import EventBrowser from './components/EventBrowser';
import LogLevelSelector from './components/LogLevelSelector';

const App = () => {
	const {
		loading,
		saving,
		hasChanges,
		events,
		settings,
		updateSetting,
		saveSettings,
		enableAllCategory,
		disableAllCategory,
		presets,
		logLevel,
		applyPreset,
	} = useEvents();

	const { addAlert } = useAlerts();

	const [search, setSearch] = useState('');

	const [status, setStatus] = useState('all');

	const [category, setCategory] = useState('all');

	const [severity, setSeverity] = useState('all');

	const handleSave = async () => {
		try {
			await saveSettings();

			addAlert({
				type: 'success',
				title: 'Success',
				description: 'Event settings saved successfully.',
			});
		} catch (error) {
			addAlert({
				type: 'error',
				title: 'Error',
				description: 'Unable to save settings.',
			});
		}
	};

	const resetFilters = () => {
		setSearch('');
		setCategory('all');
		setSeverity('all');
		setStatus('all');
	};

	if (loading) {
		return <ScreenLoader />;
	}

	return (
		<>
			<AdminPageHeader
				icon={<ProductIcon className="product-icon" />}
				title="Pastmark - Activity Logs for WordPress"
			/>

			<Content>
				<Flex gap={20}>
					<div
						style={{
							flex: 1,
						}}
					>
						<p
							style={{
								marginBottom: '20px',
								color: '#646970',
							}}
						>
							Choose which activities should be logged. Select a
							preset or customize individual actions.
						</p>
						<LogLevelSelector
							value={logLevel}
							events={events}
							settings={settings}
							onChange={applyPreset}
						/>

						<EventFilters
							events={events}
							search={search}
							status={status}
							category={category}
							severity={severity}
							onSearchChange={setSearch}
							onStatusChange={setStatus}
							onCategoryChange={setCategory}
							onSeverityChange={setSeverity}
							onReset={resetFilters}
						/>

						<EventBrowser
							events={events}
							settings={settings}
							search={search}
							status={status}
							category={category}
							severity={severity}
							onToggle={updateSetting}
						/>

						<SaveBar
							hasChanges={hasChanges}
							loading={saving}
							onSave={handleSave}
						/>
					</div>
				</Flex>
			</Content>
		</>
	);
};

export default App;
