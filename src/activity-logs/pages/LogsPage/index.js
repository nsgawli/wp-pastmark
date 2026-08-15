import React, { useEffect, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { applyFilters } from '@wordpress/hooks';
import { format } from 'date-fns';
import { parseDateOnly } from '@framework/utils/dateOnly';

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
import { fetchLogFilterOptionsByValues } from '../../services/logsApi';
import {
	buildLogDetailsPath,
	buildLogDetailsUrl,
	LOG_DETAILS_VIEW_MODES,
} from '../../utils/logDetails';
import { LOGS_PAGE_VIEW_MODES } from '../../utils/logsPageView';
import {
	SEVERITY_LABELS,
	DATE_RANGE_LABELS,
} from '../../utils/logFilterOptions';

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

// Filters whose applied value is a plain value (a user ID, an event key)
// rather than something human-readable on its own. The label for these has
// to be resolved from the server (users, events) or a static map (severity)
// instead of just stringifying the raw value.
const RESOLVABLE_FILTER_TYPES = {
	user_ids: 'users',
	event: 'events',
	ids: 'ids',
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

const formatDateForDisplay = (value) => {
	const date = parseDateOnly(value);

	return date ? format(date, 'MMM d, yyyy') : value;
};

const formatFilterValue = (key, value, resolvedLabels = {}) => {
	if (key === 'severity' && Array.isArray(value)) {
		return value
			.map((item) => SEVERITY_LABELS[item] || item)
			.join(', ');
	}

	if (RESOLVABLE_FILTER_TYPES[key] && Array.isArray(value)) {
		const labelsForKey = resolvedLabels[key] || {};

		return value
			.map((item) => labelsForKey[item] || String(item))
			.join(', ');
	}

	if (Array.isArray(value)) {
		return value.join(', ');
	}

	if (key === 'date_range' && DATE_RANGE_LABELS[value]) {
		return DATE_RANGE_LABELS[value];
	}

	if (key === 'date_from' || key === 'date_to') {
		return formatDateForDisplay(value);
	}

	return String(value);
};

const getAppliedFilters = (filters = {}, resolvedLabels = {}) => {
	const items = Object.entries(filters)
		.filter(([key, value]) => {
			if (value === undefined || value === null) {
				return false;
			}

			// Folded into the "Date" badge below instead of shown on their
			// own, since a bare "From"/"To" date only makes sense alongside
			// the custom date range it belongs to.
			if (key === 'date_from' || key === 'date_to') {
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
			value: formatFilterValue(key, value, resolvedLabels),
		}));

	const dateItem = items.find((item) => item.key === 'date_range');

	if (dateItem && filters.date_range === 'custom_range') {
		const from = filters.date_from
			? formatDateForDisplay(filters.date_from)
			: null;
		const to = filters.date_to
			? formatDateForDisplay(filters.date_to)
			: null;

		if (from && to) {
			dateItem.value = `${from} → ${to}`;
		} else if (from) {
			dateItem.value = `From ${from}`;
		} else if (to) {
			dateItem.value = `Until ${to}`;
		}
	}

	return items;
};

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

	// Applied filters for user_ids/event/ids are plain values (a user ID, an
	// event key, a log ID). Resolve them to display labels so the "Applied
	// Filters" summary shows e.g. a user's name instead of their raw ID.
	const [resolvedFilterLabels, setResolvedFilterLabels] = useState({});

	const appliedFilters = getAppliedFilters(filters, resolvedFilterLabels);

	// The advanced filters form needs `{ value, label }` pairs for its
	// user/event/ID pickers, not the plain values `filters` stores them as.
	// Reuse the labels already resolved for the "Applied Filters" summary so
	// re-opening the panel shows each selection's name/label immediately
	// instead of a blank tag while a second lookup is in flight.
	const hydratedFilters = {
		...filters,

		...Object.fromEntries(
			Object.keys(RESOLVABLE_FILTER_TYPES)
				.filter((key) => Array.isArray(filters[key]))
				.map((key) => [
					key,
					filters[key].map((value) => ({
						value,
						label: resolvedFilterLabels[key]?.[value] ?? String(value),
					})),
				])
		),
	};

	useEffect(() => {
		let cancelled = false;

		const resolveLabelsFor = async (key, type) => {
			const values = filters[key];

			if (!Array.isArray(values) || values.length === 0) {
				return [key, {}];
			}

			const options = await fetchLogFilterOptionsByValues({
				type,
				values,
			});

			return [
				key,
				options.reduce((acc, option) => {
					acc[option.value] = option.label;
					return acc;
				}, {}),
			];
		};

		Promise.all(
			Object.entries(RESOLVABLE_FILTER_TYPES).map(([key, type]) =>
				resolveLabelsFor(key, type)
			)
		).then((entries) => {
			if (cancelled) {
				return;
			}

			setResolvedFilterLabels(Object.fromEntries(entries));
		});

		return () => {
			cancelled = true;
		};
		// Only re-resolve when the resolvable filter values themselves
		// change, not on every `filters` reference change (e.g. severity).
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [filters.user_ids, filters.event, filters.ids]);

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
				title="Pastmark - Activity Logs for WordPress"
			/>

			<Content>
				<Flex vertical gap={20}>
					<Card>
						<Flex vertical gap={20}>
							<LogToolbar
								search={search}
								isRefreshing={isRefreshing}
								actions={applyFilters(
									'pastmark.activityLogs.toolbarActions',
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
									defaultValues={hydratedFilters}
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
										className="wppm-applied-filters-summary"
										align="center"
										gap={10}
										wrap
									>
										<span className="wppm-applied-filters-summary-label">
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
