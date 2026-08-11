import apiFetch from '@wordpress/api-fetch';
import apiCache from '@framework/middlewares/apiCatche';
import { __ } from '@wordpress/i18n';

export const eventTimestampOptions = [
	{ label: __('UTC', 'logtrail'), value: 'utc' },
	{ label: __('WordPress Local', 'logtrail'), value: 'local' },
]

export const logDetailsViewModeOptions = [
	{ label: __('Drawer', 'logtrail'), value: 'drawer' },
	{ label: __('Dedicated details page', 'logtrail'), value: 'single_page' },
];

export const logsPageViewModeOptions = [
	{ label: __('Table', 'logtrail'), value: 'table' },
	{ label: __('Timeline', 'logtrail'), value: 'timeline' },
];

export const autoDeleteLogsUnitOptions = [
	{ label: __('Day(s)', 'logtrail'), value: 'day' },
	{ label: __('Month(s)', 'logtrail'), value: 'month' },
	{ label: __('Year(s)', 'logtrail'), value: 'year' },
];

export const getGeneralSettings = () => {
	return apiFetch({
		path: '/logtrail/v1/settings/general-settings',
		method: 'GET',
		useApiCache: true,
	});
}

export const updateGeneralSettings = (data) => {
	apiCache.clear('/logtrail/v1/settings/general-settings');
	return apiFetch({
		path: '/logtrail/v1/settings/general-settings',
		method: 'PUT',
		data,
	});
};

export const resetGeneralSettings = () => {
	return apiFetch({
		path: '/logtrail/v1/settings/general-settings/defaults',
		method: 'GET',
		useApiCache: true,
	});
};