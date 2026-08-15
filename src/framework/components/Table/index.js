import React, { useEffect, useRef, useState } from 'react';

import { Flex, Spinner } from '@framework/components';

import './index.css';

const Table = ({
	columns = [],
	data = [],
	rowKey = 'id',
	loading = false,
	emptyText = 'No data found',
	onRowClick = null,
	selectable = false,
	selectedRows = [],
	onSelectRow = null,
	stickyHeader = false,
	sortBy = null,
	sortOrder = 'ASC',
	onSort = null,
	className = '',
	activeRowId = null,
}) => {
	const [animateRows, setAnimateRows] = useState(false);
	const prevLoadingRef = useRef(loading);
	const hasRows = data.length > 0;

	useEffect(() => {
		if (prevLoadingRef.current && !loading && hasRows) {
			setAnimateRows(true);

			const timeout = window.setTimeout(() => {
				setAnimateRows(false);
			}, 260);

			prevLoadingRef.current = loading;

			return () => {
				window.clearTimeout(timeout);
			};
		}

		prevLoadingRef.current = loading;

		return undefined;
	}, [loading, hasRows]);

	const tableClassName = `
		wppm-table-wrapper
		${stickyHeader ? 'wppm-table-sticky-header' : ''}
		${animateRows ? 'wppm-table-rows-animate' : ''}
		${className}
	`;

	const isSelected = (rowId) => {
		return selectedRows.includes(rowId);
	};

	const renderCell = (column, row) => {
		if (column.render) {
			return column.render(row[column.dataIndex], row);
		}

		return row[column.dataIndex];
	};

	const getSortKey = (column) => {
		return column.sortKey || column.dataIndex || column.key;
	};

	const isColumnSorted = (column) => {
		return sortBy === getSortKey(column);
	};

	const handleSort = (column) => {
		if (!column.sortable || !onSort) {
			return;
		}

		onSort(getSortKey(column));
	};

	const getSortIcon = (column) => {
		if (!column.sortable) {
			return null;
		}

		if (!isColumnSorted(column)) {
			return '↕';
		}

		return sortOrder === 'ASC' ? '↑' : '↓';
	};

	return (
		<div className={tableClassName}>
			{loading && hasRows && (
				<div className="wppm-table-loading-overlay">
					<Spinner />
				</div>
			)}

			<table className="wppm-table">
				<thead>
					<tr>
						{selectable && (
							<th className="wppm-table-checkbox-column"></th>
						)}

						{columns.map((column) => (
							<th
								key={column.key}
								className={`
									wppm-table-header-cell
									${column.headerClassName || column.className || ''}
									${column.sortable ? 'wppm-table-header-sortable' : ''}
									${isColumnSorted(column) ? 'wppm-table-header-sorted' : ''}
								`}
								style={{
									width: column.width || 'auto',
									textAlign: column.align || 'left',
								}}
							>
								{column.sortable ? (
									<button
										type="button"
										className={`
											wppm-table-sort-button
											wppm-table-sort-align-${column.align || 'left'}
										`}
										onClick={() => {
											handleSort(column);
										}}
									>
										<span>{column.title}</span>
										<span className="wppm-table-sort-icon">
											{getSortIcon(column)}
										</span>
									</button>
								) : (
									<span className="wppm-table-header-title">
										{column.title}
									</span>
								)}
							</th>
						))}
					</tr>
				</thead>

				<tbody>
					{loading && !hasRows && (
						<tr>
							<td
								colSpan={
									selectable
										? columns.length + 1
										: columns.length
								}
							>
								<Flex
									className="wppm-table-loader"
									justify="center"
									align="center"
								>
									<Spinner />
								</Flex>
							</td>
						</tr>
					)}

					{!loading && !hasRows && (
						<tr>
							<td
								colSpan={
									selectable
										? columns.length + 1
										: columns.length
								}
							>
								<div className="wppm-table-empty">
									{emptyText}
								</div>
							</td>
						</tr>
					)}

					{hasRows &&
						data.map((row) => {
							const rowId = row[rowKey];

							return (
								<tr
									key={rowId}
									data-row-id={rowId}
									className={`
										${onRowClick ? 'wppm-table-clickable-row' : ''}
										${activeRowId === rowId ? 'wppm-table-row-active' : ''}
									`}
									onClick={(event) => {
										if (onRowClick) {
											onRowClick(row, event);
										}
									}}
								>
									{selectable && (
										<td
											onClick={(event) => {
												event.stopPropagation();

												if (onSelectRow) {
													onSelectRow(row);
												}
											}}
										>
											<input
												type="checkbox"
												checked={isSelected(rowId)}
												onChange={() => {}}
											/>
										</td>
									)}

									{columns.map((column) => (
										<td
											key={column.key}
											className={
												column.cellClassName ||
												column.className ||
												''
											}
											style={{
												textAlign:
													column.align || 'left',
											}}
											onClick={(event) => {
												if (column.disableRowClick) {
													event.stopPropagation();
												}
											}}
										>
											{renderCell(column, row)}
										</td>
									))}
								</tr>
							);
						})}
				</tbody>
			</table>
		</div>
	);
};

export default Table;
