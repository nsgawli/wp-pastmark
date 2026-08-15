import React from 'react';

import Select from 'react-select';

import { Button, Flex } from '../../../framework/components';

const options = [
	{
		value: 'today',
		label: 'Today',
	},
	{
		value: '7days',
		label: 'Last 7 Days',
	},
	{
		value: '30days',
		label: 'Last 30 Days',
	},
	{
		value: '90days',
		label: 'Last 90 Days',
	},
];

const DashboardToolbar = ({ range, onRangeChange, onRefresh }) => {
	return (
		<div className="wppm-dashboard-toolbar">
			<Flex
				className="wppm-dashboard-toolbar-inner"
				justify="space-between"
				align="center"
				wrap
				gap={12}
			>
				<div className="wppm-dashboard-range-select">
					<Select
						options={options}
						value={options.find((item) => item.value === range)}
						isSearchable={false}
						onChange={(option) => {
							if (option) {
								onRangeChange(option.value);
							}
						}}
					/>
				</div>

				<Button
					className="wppm-dashboard-refresh-button"
					variant="secondary"
					onClick={onRefresh}
				>
					Refresh
				</Button>
			</Flex>
		</div>
	);
};

export default DashboardToolbar;
