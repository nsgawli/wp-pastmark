import apiFetch from '@wordpress/api-fetch';
import { addQueryArgs } from '@wordpress/url';

import buildLogsQuery from '../utils/buildLogsQuery';

export const fetchLogs = async ({
	page = 1,
	perPage = 20,
	search = '',
	filters = {},
	sortBy = 'timestamp',
	sortOrder = 'DESC',
} = {}) => {
	const query = buildLogsQuery({
		page,
		perPage,
		search,
		filters,
		sortBy,
		sortOrder,
	});

	const path = addQueryArgs('/pastmark/v1/logs', query);

	const response = await apiFetch({
		path,
		method: 'GET',
	});

	return {
		data: response?.data?.items || [],
		pagination: {
			current_page: response?.data?.pagination?.page || 1,

			total_pages: Math.ceil(
				(response?.data?.pagination?.total || 0) /
					(response?.data?.pagination?.per_page || 20)
			),

			total_items: response?.data?.pagination?.total || 0,
		},
	};
};

// Polled by `useNewEvents` to power the Logs page's "N new events" badge —
// how many log rows (matching the current search/filters) exist past
// `sinceId`, plus the actual latest matching id so the hook can (re)seed
// that baseline itself instead of needing a separate lookup.
export const fetchNewLogsCount = async ({
	sinceId = 0,
	search = '',
	filters = {},
} = {}) => {
	const query = buildLogsQuery({
		search,
		filters,
		includePagination: false,
	});

	query.since_id = sinceId;

	const path = addQueryArgs('/pastmark/v1/logs/new-count', query);

	const response = await apiFetch({
		path,
		method: 'GET',
	});

	return {
		count: response?.data?.count || 0,
		latestId: response?.data?.latest_id || 0,
	};
};

export const fetchLogFilterOptions = async ({
	type = '',
	search = '',
	limit = null,
} = {}) => {
	const query = {
		type,
		search,
	};

	if (limit) {
		query.limit = limit;
	}

	const path = addQueryArgs('/pastmark/v1/logs/filter-options', query);

	const response = await apiFetch({
		path,
		method: 'GET',
	});

	return response?.data?.items || [];
};

// Resolves a specific set of already-selected values (e.g. user IDs, event
// keys) to their display labels, regardless of the default result-set
// limit. Used to re-hydrate the advanced filters form with proper labels
// instead of showing blank tags for values loaded from a previous session.
export const fetchLogFilterOptionsByValues = async ({
	type = '',
	values = [],
} = {}) => {
	if (!values || values.length === 0) {
		return [];
	}

	const query = {
		type,
		values: values.join(','),
	};

	const path = addQueryArgs('/pastmark/v1/logs/filter-options', query);

	const response = await apiFetch({
		path,
		method: 'GET',
	});

	return response?.data?.items || [];
};
