import React, { useEffect, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { applyFilters } from '@wordpress/hooks';

import {
	Content,
	Flex,
	Card,
	Badge,
	Button,
	Pagination,
} from '@framework/components';

import AdminPageHeader from '@framework/components/AdminPageHeader';

import ProductIcon from '@framework/icons/productIcon';

import LogToolbar from '../../components/LogToolbar';
import LogFilters from '../../components/LogFilters';
import LogsTable from '../../components/LogsTable';
import LogsTimeline from '../../components/LogsTimeline';
import LogDetailsDrawer from '../../components/LogDetailsDrawer';

import useLogDetails from '../../hooks/useLogDetails';

import useLogs from '../../hooks/useLogs';

import SavedFilters from '../../components/SavedFilters';
import useSavedFilters from '../../hooks/useSavedFilters';

import { fetchGeneralSettings } from '../../services/generalSettingsApi';
import {
	buildLogDetailsPath,
	buildLogDetailsUrl,
	LOG_DETAILS_VIEW_MODES,
} from '../../utils/logDetails';
import { LOGS_PAGE_VIEW_MODES } from '../../utils/logsPageView';

import './index.css';

const FILTER_LABELS = {
	user_ids: 'User',
	event: 'Event',
	severity: 'Severity',
	ids: 'ID',
	date_range: 'Date',
	date_from: 'From',
	date_to: 'To',
};

const DATE_RANGE_LABELS = {
	all: 'All',
	today: 'Today',
	yesterday: 'Yesterday',
	last_7_days: 'Last 7 Days',
	last_week: 'Last Week',
	last_month: 'Last Month',
	last_30_days: 'Last 30 Days',
	custom_range: 'Custom Range',
};

const getFilterLabel = (key) => {
	if (FILTER_LABELS[key]) {
		return FILTER_LABELS[key];
	}

	return key
		.split('_')
		.filter(Boolean)
		.map((part) => part.charAt(0).toUpperCase() + part.slice(1))
		.join(' ');
};

const formatFilterValue = (value) => {
	if (Array.isArray(value)) {
		return value.join(', ');
	}

	if (typeof value === 'string' && DATE_RANGE_LABELS[value]) {
		return DATE_RANGE_LABELS[value];
	}

	return String(value);
};

const getAppliedFilters = (filters = {}) =>
	Object.entries(filters)
		.filter(([key, value]) => {
			if (value === undefined || value === null) {
				return false;
			}

			if (key === 'date_range' && String(value).trim() === 'all') {
				return false;
			}

			if (Array.isArray(value)) {
				return value.length > 0;
			}

			return String(value).trim() !== '';
		})
		.map(([key, value]) => ({
			key,
			label: getFilterLabel(key),
			value: formatFilterValue(value),
		}));

const LogsPage = () => {
	const navigate = useNavigate();

	const [initialLogId] = useState(() => {
		const params = new URLSearchParams(window.location.search);
		const logId = parseInt(params.get('log_id') || '', 10);

		return Number.isInteger(logId) && logId > 0 ? logId : null;
	});

	const {
		logs,
		loading,
		isRefreshing,

		page,
		setPage,

		perPage,

		totalPages,
		totalItems,

		search,
		setSearch,

		filters,
		setFilters,

		sortBy,
		sortOrder,
		handleSort,

		refresh,
	} = useLogs();

	const [filtersOpen, setFiltersOpen] = useState(false);

	const { log: selectedLog, loadLog, clearLog } = useLogDetails();

	const { savedFilters, deleteFilter } = useSavedFilters();

	const [detailsViewMode, setDetailsViewMode] = useState(
		LOG_DETAILS_VIEW_MODES.drawer
	);
	const [logsPageViewMode, setLogsPageViewMode] = useState(
		LOGS_PAGE_VIEW_MODES.table
	);

	const appliedFilters = getAppliedFilters(filters);

	useEffect(() => {
		if (!initialLogId) {
			return;
		}

		if (detailsViewMode === LOG_DETAILS_VIEW_MODES.singlePage) {
			navigate(buildLogDetailsPath(initialLogId));
			return;
		}

		loadLog(initialLogId);
	}, [initialLogId, detailsViewMode, loadLog, navigate]);

	useEffect(() => {
		const loadSettings = async () => {
			try {
				const settings = await fetchGeneralSettings();

				setDetailsViewMode(
					settings?.logDetailsViewMode ===
						LOG_DETAILS_VIEW_MODES.singlePage
						? LOG_DETAILS_VIEW_MODES.singlePage
						: LOG_DETAILS_VIEW_MODES.drawer
				);
				setLogsPageViewMode(
					settings?.logsPageViewMode === LOGS_PAGE_VIEW_MODES.timeline
						? LOGS_PAGE_VIEW_MODES.timeline
						: LOGS_PAGE_VIEW_MODES.table
				);
			} catch (error) {
				setDetailsViewMode(LOG_DETAILS_VIEW_MODES.drawer);
				setLogsPageViewMode(LOGS_PAGE_VIEW_MODES.table);
			}
		};

		loadSettings();
	}, []);

	const setUrlLogId = (logId) => {
		const url = new URL(window.location.href);

		if (logId) {
			url.searchParams.set('log_id', logId);
		} else {
			url.searchParams.delete('log_id');
		}

		window.history.replaceState({}, '', url.toString());
	};

	const openLogDetails = (log, event = null) => {
		if (!log?.id) {
			return;
		}

		if (detailsViewMode === LOG_DETAILS_VIEW_MODES.singlePage) {
			if (event?.metaKey || event?.ctrlKey) {
				window.open(
					buildLogDetailsUrl(log.id),
					'_blank',
					'noopener,noreferrer'
				);
				return;
			}

			navigate(buildLogDetailsPath(log.id));
			return;
		}

		setUrlLogId(log.id);
		loadLog(log.id);
	};

	return (
		<>
			<AdminPageHeader
				icon={<ProductIcon className="product-icon" />}
				title="LogTrail - User Activity Logger"
			/>

			<Content>
				<Flex vertical gap={20}>
					<Card>
						<Flex vertical gap={20}>
							<LogToolbar
								search={search}
								isRefreshing={isRefreshing}
								actions={applyFilters(
									'logtrail.activityLogs.toolbarActions',
									[],
									{ search, filters }
								)}
								onSearch={(value) => {
									setSearch(value);
									setPage(1);
								}}
								onRefresh={refresh}
								onToggleFilters={() => {
									setFiltersOpen((prev) => !prev);
								}}
							/>

							<SavedFilters
								items={savedFilters}
								onApply={(item) => {
									setFilters(item.filters);
								}}
								onDelete={deleteFilter}
							/>

							{filtersOpen && (
								<LogFilters
									defaultValues={filters}
									onApply={(values) => {
										setFilters(values);
										setPage(1);
										setFiltersOpen(false);
									}}
									onReset={() => {
										setFilters({});
										setPage(1);
									}}
									onClose={() => {
										setFiltersOpen(false);
									}}
								/>
							)}

							<Flex vertical gap={20}>
								{appliedFilters.length > 0 && (
									<Flex
										className="wptl-applied-filters-summary"
										align="center"
										gap={10}
										wrap
									>
										<span className="wptl-applied-filters-summary-label">
											Applied Filters:
										</span>

										{appliedFilters.map((item) => (
											<Badge key={item.key} type="info">
												{item.label}: {item.value}
											</Badge>
										))}

										<Button
											size="small"
											type="default"
											onClick={() => {
												setFilters({});
											}}
										>
											Clear All
										</Button>
									</Flex>
								)}

								{logsPageViewMode ===
								LOGS_PAGE_VIEW_MODES.timeline ? (
									<LogsTimeline
										data={logs}
										loading={loading}
										activeRowId={selectedLog?.id || null}
										onRowClick={(log, event) => {
											openLogDetails(log, event);
										}}
									/>
								) : (
									<LogsTable
										data={logs}
										loading={loading}
										activeRowId={selectedLog?.id || null}
										sortBy={sortBy}
										sortOrder={sortOrder}
										onSort={handleSort}
										onRowClick={(log, event) => {
											openLogDetails(log, event);
										}}
									/>
								)}

								<Pagination
									current={page}
									total={totalPages}
									totalItems={totalItems}
									perPage={perPage}
									label="Logs"
									onChange={(newPage) => {
										setPage(newPage);
									}}
								/>
							</Flex>
						</Flex>
					</Card>
				</Flex>
			</Content>

			{detailsViewMode === LOG_DETAILS_VIEW_MODES.drawer && (
				<LogDetailsDrawer
					log={selectedLog}
					isOpen={!!selectedLog}
					onClose={() => {
						setUrlLogId(null);
						clearLog();
					}}
				/>
			)}
		</>
	);
};

export default LogsPage;
