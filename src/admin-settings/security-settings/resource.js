import apiFetch from '@wordpress/api-fetch';
import apiCache from '@framework/middlewares/apiCatche';

export const getSecuritySettings = () => {
	return apiFetch({
		path: '/pastmark/v1/settings/security',
		method: 'GET',
		useApiCache: true,
	});
};

export const updateSecuritySettings = (data) => {
	apiCache.clear('/pastmark/v1/settings/security');
	return apiFetch({
		path: '/pastmark/v1/settings/security',
		method: 'PUT',
		data,
	});
};
