import React from 'react';

import { Card, EmptyState } from '../../../framework/components';

import './index.css';

const MostCommonEvents = ({ data = [] }) => {
	if (!data.length) {
		return (
			<Card>
				<h3 className="wptl-dashboard-widget-title">Most Common Events</h3>

				<EmptyState title="No events found." />
			</Card>
		);
	}

	const max = Number(data[0].total);

	return (
		<Card>
			<h3 className="wptl-dashboard-widget-title">Most Common Events</h3>

			{data.map((item) => {
				const percent = (Number(item.total) / max) * 100;

				return (
					<div
						key={item.label}
						className="wptl-dashboard-category-item"
					>
						<div className="wptl-dashboard-category-header">
							<span>{item.label}</span>

							<strong>{item.total}</strong>
						</div>

						<div className="wptl-dashboard-category-track">
							<div
								className="wptl-dashboard-category-fill"
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
