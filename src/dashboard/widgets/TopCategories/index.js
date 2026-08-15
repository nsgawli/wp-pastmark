import React from 'react';

import { Card, EmptyState } from '../../../framework/components';

import {
	ResponsiveContainer,
	BarChart,
	Bar,
	XAxis,
	YAxis,
	CartesianGrid,
	Tooltip,
	LabelList,
} from 'recharts';

import { useScreenSize } from '../../../framework/hooks/useScreenSize';
import { DASHBOARD_CHART_PALETTE } from '../../constants/palette';

import './index.css';

const CategoryTooltip = ({ active, payload }) => {
	if (!active || !payload?.length) {
		return null;
	}

	const entry = payload[0];
	const total = Number(entry?.value || 0).toLocaleString();

	return (
		<div className="wppm-dashboard-chart-tooltip">
			<div className="wppm-dashboard-chart-tooltip-label">
				{entry?.payload?.label}
			</div>
			<div className="wppm-dashboard-chart-tooltip-value">
				{total} events
			</div>
		</div>
	);
};

const TopCategories = ({ data = [] }) => {
	const { screenSize } = useScreenSize();
	const isSmallScreen = screenSize === 'xs' || screenSize === 'sm';

	if (!data.length) {
		return (
			<Card className="wppm-dashboard-bar-card">
				<h3 className="wppm-dashboard-chart-title">Top Categories</h3>

				<EmptyState title="No category data available." />
			</Card>
		);
	}

	const chartData = data.map((item) => ({
		label: item.label,
		total: Number(item.total),
	}));

	return (
		<Card className="wppm-dashboard-bar-card">
			<h3 className="wppm-dashboard-chart-title">Top Categories</h3>

			<div className="wppm-dashboard-bar-chart">
				<ResponsiveContainer width="100%" height="100%">
					<BarChart
						data={chartData}
						layout="vertical"
						margin={{ top: 4, right: 32, left: 0, bottom: 4 }}
					>
						<CartesianGrid
							horizontal={false}
							strokeDasharray="3 3"
							stroke={DASHBOARD_CHART_PALETTE.grid}
						/>

						<XAxis type="number" hide />

						<YAxis
							type="category"
							dataKey="label"
							width={isSmallScreen ? 90 : 110}
							tickLine={false}
							axisLine={false}
							tick={{
								fill: '#475467',
								fontSize: isSmallScreen ? 11 : 12,
							}}
						/>

						<Tooltip
							content={<CategoryTooltip />}
							cursor={{ fill: DASHBOARD_CHART_PALETTE.primarySoft }}
						/>

						<Bar
							dataKey="total"
							fill={DASHBOARD_CHART_PALETTE.primary}
							radius={[0, 4, 4, 0]}
							maxBarSize={20}
						>
							<LabelList
								dataKey="total"
								position="right"
								style={{
									fill: '#344054',
									fontSize: isSmallScreen ? 11 : 12,
									fontWeight: 600,
								}}
							/>
						</Bar>
					</BarChart>
				</ResponsiveContainer>
			</div>
		</Card>
	);
};

export default TopCategories;
