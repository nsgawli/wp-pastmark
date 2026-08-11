import React from 'react';

import { Avatar, Card, EmptyState } from '../../../framework/components';

import './index.css';

const MostActiveUsers = ({ data = [] }) => {
	if (!data.length) {
		return (
			<Card>
				<h3 className="wptl-dashboard-widget-title">Most Active Users</h3>

				<EmptyState title="No users found." />
			</Card>
		);
	}

	return (
		<Card>
			<h3 className="wptl-dashboard-widget-title">Most Active Users</h3>

			{data.map((item) => (
				<div key={item.user_id} className="wptl-dashboard-user-row">
					<div className="wptl-dashboard-user-cell">
						<Avatar
							src={item.avatar_url}
							name={item.name}
							size={24}
						/>

						<span>{item.name}</span>
					</div>

					<strong>{item.total}</strong>
				</div>
			))}
		</Card>
	);
};

export default MostActiveUsers;
