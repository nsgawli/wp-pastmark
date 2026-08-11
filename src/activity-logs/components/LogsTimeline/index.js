import React from 'react';
import { formatDistanceToNow } from 'date-fns';

import { Spinner, SeverityBadge } from '@framework/components';

import LogActionsDropdown from '../LogActionsDropdown';

import './index.css';

const DAY_MS = 24 * 60 * 60 * 1000;

const parseLogTimestamp = (timestamp) => {
	if (typeof timestamp !== 'string' || !timestamp.trim()) {
		return null;
	}

	const isoLike = timestamp.includes('T')
		? timestamp
		: `${timestamp.replace(' ', 'T')}Z`;

	const parsed = new Date(isoLike);

	return Number.isNaN(parsed.getTime()) ? null : parsed;
};

const startOfDay = (date) =>
	new Date(date.getFullYear(), date.getMonth(), date.getDate());

const getDayLabel = (date) => {
	if (!date) {
		return 'Unknown Date';
	}

	const diffDays = Math.round(
		(startOfDay(new Date()) - startOfDay(date)) / DAY_MS
	);

	if (diffDays === 0) {
		return 'Today';
	}

	if (diffDays === 1) {
		return 'Yesterday';
	}

	return date.toLocaleDateString(undefined, {
		year: 'numeric',
		month: 'long',
		day: 'numeric',
	});
};

const getTimeAgo = (timestamp) => {
	const parsedDate = parseLogTimestamp(timestamp);

	return parsedDate
		? formatDistanceToNow(parsedDate, { addSuffix: true })
		: null;
};

const groupLogsByDay = (logs = []) => {
	const groups = [];
	const groupsByKey = new Map();

	logs.forEach((log) => {
		const parsedDate = parseLogTimestamp(log.timestamp);
		const dayKey = parsedDate
			? startOfDay(parsedDate).getTime()
			: 'unknown';

		if (!groupsByKey.has(dayKey)) {
			const group = {
				key: dayKey,
				label: getDayLabel(parsedDate),
				items: [],
			};

			groupsByKey.set(dayKey, group);
			groups.push(group);
		}

		groupsByKey.get(dayKey).items.push(log);
	});

	return groups;
};

const LogsTimeline = ({
	data = [],
	loading = false,
	activeRowId = null,
	onRowClick = null,
	emptyText = 'No activity logs found',
}) => {
	const hasRows = data.length > 0;
	const groups = groupLogsByDay(data);

	return (
		<div className="wptl-logs-timeline">
			{loading && hasRows && (
				<div className="wptl-timeline-loading-overlay">
					<Spinner />
				</div>
			)}

			{loading && !hasRows && (
				<div className="wptl-timeline-loader">
					<Spinner />
				</div>
			)}

			{!loading && !hasRows && (
				<div className="wptl-timeline-empty">{emptyText}</div>
			)}

			{hasRows &&
				groups.map((group) => (
					<div className="wptl-timeline-group" key={group.key}>
						<div className="wptl-timeline-day-header">
							<span className="wptl-timeline-day-label">
								{group.label}
							</span>
							<span className="wptl-timeline-day-count">
								{group.items.length}{' '}
								{group.items.length === 1 ? 'event' : 'events'}
							</span>
						</div>

						<div className="wptl-timeline-list">
							{group.items.map((log) => {
								const timeAgo = getTimeAgo(log.timestamp);

								return (
									<div
										key={log.id}
										className={`wptl-timeline-item${
											activeRowId === log.id
												? ' wptl-timeline-item-active'
												: ''
										}${
											onRowClick
												? ' wptl-timeline-item-clickable'
												: ''
										}`}
										onClick={(event) => {
											if (onRowClick) {
												onRowClick(log, event);
											}
										}}
									>
										<span
											className={`wptl-timeline-marker wptl-timeline-marker-${log.severity || 'info'}`}
										/>

										<div className="wptl-timeline-content">
											<div className="wptl-timeline-item-header">
												<span className="wptl-timeline-user">
													{log.user || '-'}
												</span>

												<span className="wptl-timeline-time">
													{log.date || '-'}
												</span>

												{timeAgo && (
													<span className="wptl-timeline-time-ago">
														({timeAgo})
													</span>
												)}

												{log.severity === 'warning' && (
													<SeverityBadge
														severity={log.severity}
													/>
												)}

												<span
													className="wptl-timeline-actions"
													onClick={(event) => {
														event.stopPropagation();
													}}
												>
													<LogActionsDropdown
														log={log}
														onView={(item) => {
															if (onRowClick) {
																onRowClick(
																	item
																);
															}
														}}
													/>
												</span>
											</div>

											<div className="wptl-timeline-item-body">
												<span className="wptl-timeline-message">
													{log.message || '-'}
												</span>
											</div>

											<div className="wptl-timeline-item-footer">
												{log.action_label && (
													<span className="wptl-timeline-action">
														{log.action_label}
													</span>
												)}

												{log.action_label && (
													<span className="wptl-timeline-footer-sep">
														&bull;
													</span>
												)}

												<span className="wptl-timeline-id">
													#{log.id}
												</span>
											</div>
										</div>
									</div>
								);
							})}
						</div>
					</div>
				))}
		</div>
	);
};

export default LogsTimeline;
