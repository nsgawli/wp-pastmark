import apiFetch from '@wordpress/api-fetch';
import apiCache from '@framework/middlewares/apiCatche';
import { __ } from '@wordpress/i18n';

export const eventTimestampOptions = [
	{ label: __('UTC', 'pastmark'), value: 'utc' },
	{ label: __('WordPress Local', 'pastmark'), value: 'local' },
]

export const logDetailsViewModeOptions = [
	{ label: __('Drawer', 'pastmark'), value: 'drawer' },
	{ label: __('Dedicated details page', 'pastmark'), value: 'single_page' },
];

export const logsPageViewModeOptions = [
	{ label: __('Table', 'pastmark'), value: 'table' },
	{ label: __('Timeline', 'pastmark'), value: 'timeline' },
];

export const autoDeleteLogsUnitOptions = [
	{ label: __('Day(s)', 'pastmark'), value: 'day' },
	{ label: __('Month(s)', 'pastmark'), value: 'month' },
	{ label: __('Year(s)', 'pastmark'), value: 'year' },
];

export const getGeneralSettings = () => {
	return apiFetch({
		path: '/pastmark/v1/settings/general-settings',
		method: 'GET',
		useApiCache: true,
	});
}

export const updateGeneralSettings = (data) => {
	apiCache.clear('/pastmark/v1/settings/general-settings');
	return apiFetch({
		path: '/pastmark/v1/settings/general-settings',
		method: 'PUT',
		data,
	});
};

export const resetGeneralSettings = () => {
	return apiFetch({
		path: '/pastmark/v1/settings/general-settings/defaults',
		method: 'GET',
		useApiCache: true,
	});
};