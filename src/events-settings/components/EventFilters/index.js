import React from 'react';

import { Flex, SearchInput, Button } from '@framework/components';

const EventFilters = ({
	events = {},
	search = '',
	status = 'all',
	category = 'all',
	severity = 'all',
	onSearchChange = null,
	onStatusChange = null,
	onCategoryChange = null,
	onSeverityChange = null,
	onReset = null,
}) => {
	const categoryOptions = [
		{
			value: 'all',
			label: 'All Categories',
		},
		...Object.entries(events).map(([key, event]) => ({
			value: key,
			label: event.label,
		})),
	];

	const hasFilters =
		search || category !== 'all' || severity !== 'all' || status !== 'all';

	return (
		<Flex
			gap={15}
			style={{
				marginBottom: '20px',
			}}
		>
			<div style={{ flex: 1 }}>
				<SearchInput
					placeholder="Search actions..."
					value={search}
					onChange={(value) => {
						if (onSearchChange) {
							onSearchChange(value);
						}
					}}
				/>
			</div>

			<select
				style={{
					minWidth: '200px',
					minHeight: '40px',
					padding: '0 12px',
					border: '1px solid #d0d4da',
					borderRadius: '6px',
				}}
				value={category}
				onChange={(event) => {
					if (onCategoryChange) {
						onCategoryChange(event.target.value);
					}
				}}
			>
				{categoryOptions.map((option) => (
					<option key={option.value} value={option.value}>
						{option.label}
					</option>
				))}
			</select>

			<select
				style={{
					minWidth: '180px',
					minHeight: '40px',
					padding: '0 12px',
					border: '1px solid #d0d4da',
					borderRadius: '6px',
				}}
				value={severity}
				onChange={(event) => {
					if (onSeverityChange) {
						onSeverityChange(event.target.value);
					}
				}}
			>
				<option value="all">All Severity</option>
				<option value="info">Info</option>
				<option value="warning">Warning</option>
				<option value="error">Error</option>
				<option value="critical">Critical</option>
			</select>

			<select
				style={{
					minWidth: '180px',
					minHeight: '40px',
					padding: '0 12px',
					border: '1px solid #d0d4da',
					borderRadius: '6px',
				}}
				value={status}
				onChange={(event) => {
					if (onStatusChange) {
						onStatusChange(event.target.value);
					}
				}}
			>
				<option value="all">All Actions</option>
				<option value="enabled">Enabled Only</option>
				<option value="disabled">Disabled Only</option>
			</select>

			{hasFilters && (
				<Button variant="secondary" onClick={onReset}>
					Reset Filters
				</Button>
			)}
		</Flex>
	);
};

export default EventFilters;
