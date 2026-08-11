import React from 'react';

import { applyFilters } from '@wordpress/hooks';

import { Table, SeverityBadge, EventBadge } from '@framework/components';

import LogActionsDropdown from '../LogActionsDropdown';

import './index.css';

const LogsTable = ({
	data = [],
	loading = false,
	sortBy,
	sortOrder,
	onSort,
	density = 'comfortable',
	activeRowId = null,
	onRowClick = null,
}) => {
	const formatLogDate = (value) => {
		if (typeof value === 'string' && value.trim()) {
			return value;
		}

		const timestamp = Number(value);

		if (Number.isFinite(timestamp)) {
			const milliseconds =
				timestamp > 1e12 ? timestamp : timestamp * 1000;

			return new Date(milliseconds).toLocaleString();
		}

		return value || '-';
	};

	const columns = [
		{
			key: 'id',
			title: 'ID',
			dataIndex: 'id',
			sortable: true,
			sortKey: 'id',
			width: 90,
			className: 'wptl-col-id wptl-col-numeric',
		},
		{
			key: 'date',
			title: 'Date',
			dataIndex: 'date',
			sortable: true,
			sortKey: 'timestamp',
			width: 180,
			className: 'wptl-col-date wptl-col-numeric',
			render: (value) => formatLogDate(value),
		},
		{
			key: 'user',
			title: 'User',
			dataIndex: 'user',
			width: 140,
			className: 'wptl-col-user',
		},
		{
			key: 'event',
			title: 'Event',
			dataIndex: 'event',
			sortable: true,
			sortKey: 'event_type',
			className: 'wptl-col-event',
			render: (value) => {
				return <EventBadge event={value} />;
			},
		},
		{
			key: 'action',
			title: 'Action',
			dataIndex: 'action',
			width: 150,
			className: 'wptl-col-action',
			render: (_, row) => row?.action_label || '-',
		},
		{
			key: 'message',
			title: 'Message',
			dataIndex: 'message',
			className: 'wptl-col-message',
			render: (value) => {
				return (
					<span className="wptl-log-message" title={value || ''}>
						{value || '-'}
					</span>
				);
			},
		},
		{
			key: 'severity',
			title: 'Severity',
			dataIndex: 'severity',
			sortable: true,
			sortKey: 'severity',
			width: 140,
			className: 'wptl-col-severity',
			render: (value) => {
				return <SeverityBadge severity={value} />;
			},
		},
		{
			key: 'actions',
			title: 'Actions',
			width: 110,
			align: 'right',
			disableRowClick: true,
			className: 'wptl-col-actions',
			render: (_, row) => {
				return (
					<LogActionsDropdown
						log={row}
						onView={(log) => {
							if (onRowClick) {
								onRowClick(log);
							}
						}}
					/>
				);
			},
		},
	];

	/**
	 * Extension point: add-ons (e.g. logtrail-pro) can inject, reorder, or
	 * remove log-list columns by filtering this array.
	 */
	const filteredColumns = applyFilters(
		'logtrail.activityLogs.columns',
		columns
	);

	return (
		<div className="wptl-logs-table">
			<Table
				className={`wptl-logs-table-density-${density}`}
				columns={filteredColumns}
				data={data}
				rowKey="id"
				activeRowId={activeRowId}
				loading={loading}
				sortBy={sortBy}
				sortOrder={sortOrder}
				onSort={onSort}
				stickyHeader
				emptyText="No activity logs found"
				onRowClick={onRowClick}
			/>

			<div className="wptl-logs-table-scroll-hint" aria-hidden="true">
				Swipe horizontally to view more columns
			</div>
		</div>
	);
};

export default LogsTable;
