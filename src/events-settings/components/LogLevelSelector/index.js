import React, { useMemo } from 'react';

import { Card, Flex, Badge } from '@framework/components';
import './index.css';

const OPTIONS = [
	{
		key: 'essential',
		label: 'Essential',
		description: 'Critical security and site changes only.',
	},
	{
		key: 'recommended',
		label: 'Recommended',
		description: 'Best balance for most websites.',
	},
	{
		key: 'complete',
		label: 'Complete',
		description: 'Log all available activities.',
	},
	{
		key: 'custom',
		label: 'Custom',
		description: 'Manually configured settings.',
	},
];

const getActionKey = (action) => {
	if (typeof action === 'string') {
		return action;
	}

	return action?.key;
};

const LogLevelSelector = ({
	value,
	onChange,
	events = {},
	settings = {},
}) => {
	const counts = useMemo(() => {
		let total = 0;
		let active = 0;

		Object.entries(events).forEach(([eventKey, event]) => {
			const actions = Array.isArray(event?.actions) ? event.actions : [];

			actions.forEach((action) => {
				const actionKey = getActionKey(action);

				if (!actionKey) {
					return;
				}

				total += 1;

				if (Boolean(settings?.[eventKey]?.[actionKey])) {
					active += 1;
				}
			});
		});

		return {
			total,
			active,
			inactive: Math.max(total - active, 0),
		};
	}, [events, settings]);

	return (
		<>
			<Flex
				gap={15}
				style={{
					marginBottom: '12px',
				}}
			>
				{OPTIONS.map((option) => (
					<Card
						key={option.key}
						clickable
						onClick={() => {
							if (option.key === 'custom') {
								return;
							}

							onChange(option.key);
						}}
						className={
							value === option.key ? 'wppm-log-level-active' : ''
						}
						style={{
							flex: 1,
						}}
					>
						<div className="wppm-log-level-card-content">
							<strong className="wppm-log-level-title">
								{option.label}
							</strong>

							<div className="wppm-log-level-description">
								{option.description}
							</div>

							{value === option.key && (
								<Badge
									type="success"
									className="wppm-log-level-active-badge"
								>
									Active
								</Badge>
							)}
						</div>
					</Card>
				))}
			</Flex>

			<div className="wppm-log-level-counters" aria-live="polite">
				<Badge type="info">Total Registered: {counts.total}</Badge>
				<Badge type="success">Active: {counts.active}</Badge>
				<Badge type="warning">Inactive: {counts.inactive}</Badge>
			</div>
		</>
	);
};

export default LogLevelSelector;
