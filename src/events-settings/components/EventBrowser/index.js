import React, { useState } from 'react';

import { Card, Badge, Button, EmptyState, Flex } from '@framework/components';

import EventSwitch from '../EventSwitch';

import './index.css';

const EventBrowser = ({
	events = {},
	settings = {},
	search = '',
	category = 'all',
	status = 'all',
	severity = 'all',
	onToggle = null,
}) => {
	const [expanded, setExpanded] = useState(() => {
		const initialExpanded = {};

		Object.keys(events).forEach((eventKey) => {
			initialExpanded[eventKey] = false;
		});

		return initialExpanded;
	});

	const toggleCategory = (eventKey) => {
		setExpanded((prev) => ({
			...prev,
			[eventKey]: !prev[eventKey],
		}));
	};

	const enableAll = (eventKey, actions) => {
		actions.forEach((action) => {
			onToggle(eventKey, action.key, true);
		});
	};

	const disableAll = (eventKey, actions) => {
		actions.forEach((action) => {
			onToggle(eventKey, action.key, false);
		});
	};

	const searchRows = [];
	const groups = {};

	Object.entries(events).forEach(([eventKey, event]) => {
		if (category !== 'all' && category !== eventKey) {
			return;
		}

		const actions = event.actions.filter((action) => {
			const enabled = settings?.[eventKey]?.[action.key] ?? true;

			if (status === 'enabled' && !enabled) {
				return false;
			}

			if (status === 'disabled' && enabled) {
				return false;
			}

			if (severity !== 'all' && action.severity !== severity) {
				return false;
			}

			if (
				search &&
				!`
								${action.label}
								${action.description}
								${event.label}
							`
					.toLowerCase()
					.includes(search.toLowerCase())
			) {
				return false;
			}

			return true;
		});

		if (!actions.length) {
			return;
		}

		groups[eventKey] = {
			label: event.label,
			actions,
		};

		actions.forEach((action) => {
			searchRows.push({
				eventKey,
				eventLabel: event.label,
				action,
				enabled: settings?.[eventKey]?.[action.key] ?? true,
			});
		});
	});

	if (search.trim().length > 0) {
		return (
			<div className="wptl-event-browser">
				{searchRows.map((row) => (
					<Card
						key={`${row.eventKey}-${row.action.key}`}
						className="wptl-events-card"
					>
						<Flex justify="space-between" align="center">
							<div className="wptl-event-info">
								<div className="wptl-event-title">
									{row.action.label}
								</div>

								{row.action.description && (
									<div className="wptl-event-description">
										{row.action.description}
									</div>
								)}

								<div
									style={{
										marginTop: '6px',
									}}
								>
									<Badge>{row.eventLabel}</Badge>
								</div>
							</div>

							<EventSwitch
								checked={row.enabled}
								onChange={(value) =>
									onToggle(
										row.eventKey,
										row.action.key,
										value
									)
								}
							/>
						</Flex>
					</Card>
				))}
			</div>
		);
	}

	if (!Object.keys(groups).length) {
		return (
			<EmptyState
				title="No Actions Found"
				description="Try changing filters."
			/>
		);
	}

	return (
		<div className="wptl-event-browser">
			{Object.entries(groups).map(([eventKey, group]) => {
				const enabledCount = group.actions.filter(
					(action) => settings?.[eventKey]?.[action.key] ?? true
				).length;
				const isExpanded = !!expanded[eventKey];

				return (
					<Card
						key={eventKey}
						className="wptl-events-card"
						clickable
						onClick={() => toggleCategory(eventKey)}
					>
						<div className="wptl-event-group-header">
							<Flex
								className="wptl-event-group-meta"
								align="center"
								gap={10}
							>
								<button
									type="button"
									className="wptl-event-group-toggle"
									aria-expanded={isExpanded}
								>
									<span
										aria-hidden="true"
										className="wptl-event-group-chevron"
									>
										{isExpanded ? '▼' : '▶'}
									</span>
									<span>{group.label}</span>
								</button>

								<Badge>
									{enabledCount}/{group.actions.length}
								</Badge>
							</Flex>

							<Flex className="wptl-event-group-controls" gap={8}>
								<Button
									size="small"
									className="wptl-event-group-button"
									onClick={(event) => {
										event.stopPropagation();
										enableAll(eventKey, group.actions);
									}}
								>
									Enable All
								</Button>

								<Button
									size="small"
									className="wptl-event-group-button"
									onClick={(event) => {
										event.stopPropagation();
										disableAll(eventKey, group.actions);
									}}
								>
									Disable All
								</Button>
							</Flex>
						</div>

						{expanded[eventKey] && (
							// eslint-disable-next-line jsx-a11y/no-static-element-interactions, jsx-a11y/click-events-have-key-events -- stops the click from bubbling to the card's expand/collapse handler; no new keyboard interaction is introduced here.
							<div
								className="wptl-event-actions"
								onClick={(event) => event.stopPropagation()}
							>
								{group.actions.map((action) => {
									const enabled =
										settings?.[eventKey]?.[action.key] ??
										true;

									return (
										<div
											key={action.key}
											className="wptl-event-row"
										>
											<div className="wptl-event-main">
												<div className="wptl-event-info">
													<div className="wptl-event-title">
														{action.label}
													</div>

													{action.description && (
														<div className="wptl-event-description">
															{action.description}
														</div>
													)}
												</div>

												<div className="wptl-event-meta">
													<Badge>
														{action.severity_label ||
															action.severity}
													</Badge>
												</div>
											</div>

											<EventSwitch
												checked={enabled}
												onChange={(value) =>
													onToggle(
														eventKey,
														action.key,
														value
													)
												}
											/>
										</div>
									);
								})}
							</div>
						)}
					</Card>
				);
			})}
		</div>
	);
};

export default EventBrowser;
