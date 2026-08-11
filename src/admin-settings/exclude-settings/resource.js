import apiFetch from '@wordpress/api-fetch';
import apiCache from '@framework/middlewares/apiCatche';
import { addQueryArgs } from '@wordpress/url';

export const getExcludeSettings = () => {
	return apiFetch({
		path: '/logtrail/v1/settings/exclude-settings',
		method: 'GET',
		useApiCache: true,
	});
};

export const updateExcludeSettings = (data) => {
	apiCache.clear('/logtrail/v1/settings/exclude-settings');

	return apiFetch({
		path: '/logtrail/v1/settings/exclude-settings',
		method: 'PUT',
		data,
	});
};

export const resetExcludeSettings = () => {
	return apiFetch({
		path: '/logtrail/v1/settings/exclude-settings/defaults',
		method: 'GET',
		useApiCache: true,
	});
};

export const getExcludeOptions = () => {
	return apiFetch({
		path: '/logtrail/v1/settings/exclude-settings/options',
		method: 'GET',
		useApiCache: true,
	});
};

export const loadUsers = async (keyword = '') => {
	const path = addQueryArgs('/logtrail/v1/settings/exclude-settings/users', {
		search: keyword,
	});

	const response = await apiFetch({
		path,
	});

	return response?.data ?? response;
};

export const loadPlugins = async (keyword = '') => {
	const path = addQueryArgs(
		'/logtrail/v1/settings/exclude-settings/plugins',
		{
			search: keyword,
		}
	);

	const response = await apiFetch({
		path,
	});

	return response?.data ?? response;
};

export const loadThemes = async (keyword = '') => {
	const path = addQueryArgs(
		'/logtrail/v1/settings/exclude-settings/themes',
		{
			search: keyword,
		}
	);

	const response = await apiFetch({
		path,
	});

	return response?.data ?? response;
};
