import { useEffect, useState } from 'react';

import { fetchLogs } from '../services/logsApi';

const PAGE_URL_PARAM = 'log_page';
const FILTER_COOKIE_NAME = 'pastmark_activity_log_filters';
const COOKIE_MAX_AGE_SECONDS = 60 * 60 * 24 * 30;
const FILTER_SCHEMA = {
	user_ids: 'array-int',
	event: 'array-text',
	severity: 'array-text',
	ids: 'array-int',
	date_range: 'text',
	date_from: 'text',
	date_to: 'text',
};

const getInitialFiltersFromServer = () => {
	if (typeof window === 'undefined') {
		return {};
	}

	const serverFilters =
		window.pastmarkActivityLogsConfig?.initialAdvancedFilters;

	if (!serverFilters || typeof serverFilters !== 'object') {
		return {};
	}

	return normalizeFilters(serverFilters);
};

const isFilterValueSet = (value) => {
	if (value === undefined || value === null) {
		return false;
	}

	if (Array.isArray(value)) {
		return value.length > 0;
	}

	return String(value).trim() !== '';
};

const normalizeFilters = (filters = {}) => (
	Object.entries(FILTER_SCHEMA).reduce((acc, [key, type]) => {
		const value = filters[key];

		if (!isFilterValueSet(value)) {
			if (key === 'date_range') {
				acc[key] = 'all';
			}

			return acc;
		}

		if (type === 'array-int') {
			const items = (Array.isArray(value)
				? value
				: String(value).split(','))
				.map((item) => parseInt(item, 10))
				.filter((item) => Number.isInteger(item) && item > 0);

			if (items.length > 0) {
				acc[key] = Array.from(new Set(items));
			}

			return acc;
		}

		if (type === 'array-text') {
			const items = (Array.isArray(value)
				? value
				: String(value).split(','))
				.map((item) => String(item).trim())
				.filter(Boolean);

			if (items.length > 0) {
				acc[key] = Array.from(new Set(items));
			}

			return acc;
		}

		const normalizedValue = String(value).trim();

		if (key === 'date_range' && normalizedValue === '') {
			acc[key] = 'all';
			return acc;
		}

		if (normalizedValue !== '') {
			acc[key] = normalizedValue;
		}

		return acc;
	}, {})
);

const getInitialPageFromUrl = () => {
	if (typeof window === 'undefined') {
		return 1;
	}

	const params = new URLSearchParams(window.location.search);
	const page = parseInt(params.get(PAGE_URL_PARAM) || '', 10);

	return Number.isInteger(page) && page > 0 ? page : 1;
};

const writePageToUrl = (page) => {
	if (typeof window === 'undefined') {
		return;
	}

	const url = new URL(window.location.href);

	if (page && page > 1) {
		url.searchParams.set(PAGE_URL_PARAM, page);
	} else {
		url.searchParams.delete(PAGE_URL_PARAM);
	}

	window.history.replaceState({}, '', url.toString());
};

const readFiltersFromCookie = () => {
	if (typeof window === 'undefined') {
		return {};
	}

	const allCookies = document.cookie ? document.cookie.split('; ') : [];
	const cookieValue = allCookies.find((cookie) => (
		cookie.startsWith(`${FILTER_COOKIE_NAME}=`)
	));

	if (!cookieValue) {
		return {};
	}

	const encodedJson = cookieValue.substring(FILTER_COOKIE_NAME.length + 1);

	try {
		const parsed = JSON.parse(decodeURIComponent(encodedJson));

		if (!parsed || typeof parsed !== 'object' || Array.isArray(parsed)) {
			return {};
		}

		return normalizeFilters(parsed);
	} catch (error) {
		return {};
	}
};

const writeFiltersToCookie = (filters = {}) => {
	if (typeof document === 'undefined') {
		return;
	}

	const normalizedFilters = normalizeFilters(filters);
	const hasFilters = Object.keys(normalizedFilters).length > 0;

	if (!hasFilters) {
		document.cookie = `${FILTER_COOKIE_NAME}=; path=/; max-age=0; samesite=lax`;

		return;
	}

	document.cookie = `${FILTER_COOKIE_NAME}=${encodeURIComponent(
		JSON.stringify(normalizedFilters)
	)}; path=/; max-age=${COOKIE_MAX_AGE_SECONDS}; samesite=lax`;
};

const useLogs = () => {
	const [logs, setLogs] = useState([]);
	const [loading, setLoading] = useState(false);
	const [isRefreshing, setIsRefreshing] = useState(false);

	const [page, setPage] = useState(getInitialPageFromUrl);
	const [perPage] = useState(20);

	const [totalPages, setTotalPages] = useState(1);
	const [totalItems, setTotalItems] = useState(0);

	const [search, setSearch] = useState('');
	const [filters, setFilters] = useState(() => {
		const serverFilters = getInitialFiltersFromServer();

		if (Object.keys(serverFilters).length > 0) {
			return serverFilters;
		}

		return readFiltersFromCookie();
	});

	const [sortBy, setSortBy] = useState('timestamp');
	const [sortOrder, setSortOrder] = useState('DESC');

	const loadLogs = async ({ manual = false } = {}) => {
		if (manual) {
			setIsRefreshing(true);
		}

		setLoading(true);

		try {
			const response = await fetchLogs({
				page,
				perPage,
				search,
				filters,
				sortBy,
				sortOrder,
			});

			setLogs(response.data || []);

			setTotalPages(response.pagination?.total_pages || 1);

			setTotalItems(response.pagination?.total_items || 0);
		} finally {
			setLoading(false);

			if (manual) {
				setIsRefreshing(false);
			}
		}
	};

	useEffect(() => {
		loadLogs();
	}, [page, perPage, search, filters, sortBy, sortOrder]);

	useEffect(() => {
		writeFiltersToCookie(filters);
	}, [filters]);

	useEffect(() => {
		writePageToUrl(page);
	}, [page]);

	const handleSort = (column) => {
		if (sortBy === column) {
			setSortOrder(sortOrder === 'ASC' ? 'DESC' : 'ASC');

			return;
		}

		setSortBy(column);
		setSortOrder('ASC');
	};

	return {
		logs,
		loading,
		isRefreshing,

		page,
		setPage,

		perPage,

		totalPages,
		totalItems,

		search,

		setSearch: (value) => {
			setPage(1);
			setSearch(value);
		},

		filters,

		setFilters: (value) => {
			setPage(1);
			setFilters(normalizeFilters(value));
		},

		sortBy,
		sortOrder,
		handleSort,

		refresh: () => loadLogs({ manual: true }),
	};
};

export default useLogs;
