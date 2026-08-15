import React from 'react';

import { Card, EmptyState } from '../../../framework/components';

import './index.css';

const MostCommonEvents = ({ data = [] }) => {
	if (!data.length) {
		return (
			<Card>
				<h3 className="wppm-dashboard-widget-title">Most Common Events</h3>

				<EmptyState title="No events found." />
			</Card>
		);
	}

	const max = Number(data[0].total);

	return (
		<Card>
			<h3 className="wppm-dashboard-widget-title">Most Common Events</h3>

			{data.map((item) => {
				const percent = (Number(item.total) / max) * 100;

				return (
					<div
						key={item.label}
						className="wppm-dashboard-category-item"
					>
						<div className="wppm-dashboard-category-header">
							<span>{item.label}</span>

							<strong>{item.total}</strong>
						</div>

						<div className="wppm-dashboard-category-track">
							<div
								className="wppm-dashboard-category-fill"
								style={{
									width: `${percent}%`,
								}}
							/>
						</div>
					</div>
				);
			})}
		</Card>
	);
};

export default MostCommonEvents;
