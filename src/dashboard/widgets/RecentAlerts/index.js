import React from 'react';
import { format, formatDistanceToNow } from 'date-fns';

import {
	Avatar,
	Card,
	EmptyState,
	EventBadge,
	SeverityBadge,
} from '@framework/components';

import './index.css';

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

const formatAlertTime = (timestamp) => {
	const parsedDate = parseLogTimestamp(timestamp);

	if (!parsedDate) {
		return timestamp || '-';
	}

	const formatted = format(parsedDate, 'MMMM d,yyyy h:mm a');
	const timeAgo = formatDistanceToNow(parsedDate, { addSuffix: true });

	return `${formatted} (${timeAgo})`;
};

const RecentAlerts = ({ data = [] }) => {
	return (
		<Card>
			<h3 className="wptl-dashboard-widget-title">
				Recent High-Severity Events
			</h3>

			<div className="wptl-dashboard-alert-table-wrap">
				<table className="wptl-dashboard-alert-table">
					<thead>
						<tr>
							<th className="wptl-dashboard-col-event">Event</th>

							<th className="wptl-dashboard-col-severity">
								Severity
							</th>

							<th className="wptl-dashboard-col-user">User</th>

							<th className="wptl-dashboard-col-message">
								Message
							</th>

							<th className="wptl-dashboard-col-time">Time</th>
						</tr>
					</thead>

					<tbody>
						{!data.length && (
							<tr>
								<td
									className="wptl-dashboard-empty-row"
									colSpan={5}
								>
									<EmptyState title="0 records found" />
								</td>
							</tr>
						)}

						{data.map((item) => (
							<tr key={item.id}>
								<td className="wptl-dashboard-col-event">
									<EventBadge event={item.event_label} />
								</td>

								<td className="wptl-dashboard-col-severity">
									<SeverityBadge severity={item.severity} />
								</td>

								<td className="wptl-dashboard-col-user">
									<div className="wptl-dashboard-user-cell">
										<Avatar
											src={item.avatar_url}
											name={item.user_name || 'System'}
											size={24}
										/>

										<span>
											{item.user_name || 'System'}
										</span>
									</div>
								</td>

								<td
									className="wptl-dashboard-col-message"
									title={item.message || ''}
								>
									{item.message || '-'}
								</td>

								<td className="wptl-dashboard-col-time">
									{formatAlertTime(item.timestamp)}
								</td>
							</tr>
						))}
					</tbody>
				</table>
			</div>
		</Card>
	);
};

export default RecentAlerts;
