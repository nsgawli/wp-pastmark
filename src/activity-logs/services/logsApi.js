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

	const path = addQueryArgs('/logtrail/v1/logs', query);

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

export const fetchLogFilterOptions = async ({
	type = '',
	search = '',
} = {}) => {
	const query = {
		type,
		search,
	};

	const path = addQueryArgs('/logtrail/v1/logs/filter-options', query);

	const response = await apiFetch({
		path,
		method: 'GET',
	});

	return response?.data?.items || [];
};
