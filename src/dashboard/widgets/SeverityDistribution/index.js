import React from 'react';

import { Card, EmptyState } from '../../../framework/components';

import {
	PieChart,
	Pie,
	Cell,
	Tooltip,
	ResponsiveContainer,
	Legend,
} from 'recharts';

import { useScreenSize } from '../../../framework/hooks/useScreenSize';
import { DASHBOARD_SEVERITY_COLORS } from '../../constants/palette';

import './index.css';

const SeverityTooltip = ({ active, payload }) => {
	if (!active || !payload?.length) {
		return null;
	}

	const entry = payload[0];
	const value = Number(entry?.value || 0).toLocaleString();

	return (
		<div className="wppm-dashboard-chart-tooltip">
			<div className="wppm-dashboard-chart-tooltip-label">
				{entry?.name}
			</div>
			<div className="wppm-dashboard-chart-tooltip-value">
				{value} events
			</div>
		</div>
	);
};

const SeverityDistribution = ({ data = [] }) => {
	const { screenSize } = useScreenSize();
	const isSmallScreen = screenSize === 'xs' || screenSize === 'sm';

	if (!data.length) {
		return (
			<Card className="wppm-dashboard-donut-card">
				<h3 className="wppm-dashboard-chart-title">
					Severity Distribution
				</h3>

				<EmptyState title="No severity data available." />
			</Card>
		);
	}

	const chartData = data.map((item) => ({
		label: item.label,
		total: Number(item.total),
	}));

	return (
		<Card className="wppm-dashboard-donut-card">
			<h3 className="wppm-dashboard-chart-title">
				Severity Distribution
			</h3>

			<div className="wppm-dashboard-donut-chart">
				<ResponsiveContainer width="100%" height="100%">
					<PieChart>
						<Pie
							data={chartData}
							dataKey="total"
							nameKey="label"
							cx="50%"
							cy={isSmallScreen ? '42%' : '50%'}
							innerRadius={isSmallScreen ? 40 : 50}
							outerRadius={isSmallScreen ? 72 : 85}
							paddingAngle={2}
						>
							{chartData.map((item, index) => (
								<Cell
									key={item.label}
									fill={
										DASHBOARD_SEVERITY_COLORS[
											index %
												DASHBOARD_SEVERITY_COLORS.length
										]
									}
								/>
							))}
						</Pie>

						<Tooltip content={<SeverityTooltip />} />

						<Legend
							layout={isSmallScreen ? 'horizontal' : 'vertical'}
							verticalAlign={isSmallScreen ? 'bottom' : 'middle'}
							align={isSmallScreen ? 'center' : 'right'}
							iconType="circle"
							iconSize={8}
							wrapperStyle={{
								fontSize: isSmallScreen ? '12px' : '13px',
								lineHeight: '1.35',
								color: '#344054',
							}}
						/>
					</PieChart>
				</ResponsiveContainer>
			</div>
		</Card>
	);
};

export default SeverityDistribution;
