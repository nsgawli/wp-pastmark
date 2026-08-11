import React from 'react';

import { Card, EmptyState } from '../../../framework/components';

import {
	ResponsiveContainer,
	AreaChart,
	Area,
	XAxis,
	YAxis,
	CartesianGrid,
	Tooltip,
} from 'recharts';

import { useScreenSize } from '../../../framework/hooks/useScreenSize';
import { DASHBOARD_CHART_PALETTE } from '../../constants/palette';

import './index.css';

const TimelineTooltip = ({ active, payload, label }) => {
	if (!active || !payload?.length) {
		return null;
	}

	const total = Number(payload[0]?.value || 0).toLocaleString();

	return (
		<div className="wptl-dashboard-chart-tooltip">
			<div className="wptl-dashboard-chart-tooltip-label">{label}</div>
			<div className="wptl-dashboard-chart-tooltip-value">
				{total} events
			</div>
		</div>
	);
};

const ActivityTimeline = ({ data = [], loading = false, range = '' }) => {
	const { screenSize } = useScreenSize();
	const isSmallScreen = screenSize === 'xs' || screenSize === 'sm';
	const chartHeight = isSmallScreen ? 260 : 320;
	const xTickFontSize = isSmallScreen ? 11 : 12;
	const yTickFontSize = isSmallScreen ? 11 : 12;
	const xTickGap = isSmallScreen ? 28 : 20;

	// The 90-day range is daily-bucketed (~90 points), which is too dense
	// for one label per tick, so space labels a week (7 days) apart.
	// `interval` is a 0-based skip count, so 6 shows every 7th point.
	const xTickInterval = range === '90days' ? 6 : 'preserveStartEnd';

	// A single data point (e.g. the "Today" range) has nothing to draw a
	// line between, so Recharts renders it as a lone dot. Prepend a zero
	// anchor point to give it a line to draw.
	const chartData =
		data.length === 1
			? [{ label: '', total: 0 }, ...data]
			: data;

	return (
		<Card>
			<div className="wptl-dashboard-widget-header">
				<div className="wptl-dashboard-widget-title">
					Activity Timeline
				</div>
			</div>

			{loading && (
				<div
					className="wptl-dashboard-chart-loading"
					style={{ height: chartHeight }}
				>
					Loading...
				</div>
			)}

			{!loading && data.length === 0 && (
				<EmptyState
					title="No activity found"
					description="Timeline will appear here."
				/>
			)}

			{!loading && data.length > 0 && (
				<div
					className="wptl-dashboard-chart-container"
					style={{ height: chartHeight }}
				>
					<ResponsiveContainer width="100%" height="100%">
						<AreaChart data={chartData}>
							<CartesianGrid
								strokeDasharray="3 3"
								stroke={DASHBOARD_CHART_PALETTE.grid}
							/>

							<XAxis
								dataKey="label"
								interval={xTickInterval}
								minTickGap={xTickGap}
								tick={{
									fill: '#475467',
									fontSize: xTickFontSize,
								}}
								tickLine={false}
								axisLine={{
									stroke: DASHBOARD_CHART_PALETTE.axis,
								}}
								height={34}
							/>

							<YAxis
								allowDecimals={false}
								tick={{
									fill: '#667085',
									fontSize: yTickFontSize,
								}}
								tickLine={false}
								axisLine={false}
								width={32}
							/>

							<Tooltip
								content={<TimelineTooltip />}
								cursor={{
									stroke: DASHBOARD_CHART_PALETTE.cursor,
									strokeDasharray: '4 4',
								}}
							/>

							<Area
								type="monotone"
								dataKey="total"
								stroke={DASHBOARD_CHART_PALETTE.primary}
								strokeWidth={2}
								fill={DASHBOARD_CHART_PALETTE.primarySoft}
								fillOpacity={1}
								activeDot={{
									r: 4,
									strokeWidth: 0,
									fill: DASHBOARD_CHART_PALETTE.primary,
								}}
							/>
						</AreaChart>
					</ResponsiveContainer>
				</div>
			)}
		</Card>
	);
};

export default ActivityTimeline;
