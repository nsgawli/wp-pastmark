import React from 'react';

import { Badge, Card, EmptyState } from '../../../framework/components';

import './index.css';

const getRisk = (total) => {
	if (total >= 10) {
		return { type: 'error', label: 'High' };
	}

	if (total >= 5) {
		return { type: 'warning', label: 'Medium' };
	}

	return { type: 'info', label: 'Low' };
};

const FailedLogins = ({ data = [] }) => {
	if (!data.length) {
		return (
			<Card>
				<h3 className="wptl-dashboard-widget-title">
					Failed Login Attempts
				</h3>

				<EmptyState
					title="No failed login attempts"
					description="Source IPs behind failed logins will appear here."
				/>
			</Card>
		);
	}

	return (
		<Card>
			<h3 className="wptl-dashboard-widget-title">
				Failed Login Attempts
			</h3>

			<div className="wptl-dashboard-failed-login-table-wrap">
				<table className="wptl-dashboard-failed-login-table">
					<thead>
						<tr>
							<th>IP Address</th>
							<th>User</th>
							<th>Attempts</th>
							<th>Risk</th>
						</tr>
					</thead>

					<tbody>
						{data.map((item) => {
							const risk = getRisk(Number(item.total));

							return (
								<tr key={`${item.label}-${item.username}`}>
									<td className="wptl-dashboard-failed-login-ip">
										{item.label}
									</td>

									<td>{item.username || '—'}</td>

									<td>{item.total}</td>

									<td>
										<Badge type={risk.type}>
											{risk.label}
										</Badge>
									</td>
								</tr>
							);
						})}
					</tbody>
				</table>
			</div>
		</Card>
	);
};

export default FailedLogins;
