import apiFetch from '@wordpress/api-fetch';
import apiCache from '@framework/middlewares/apiCatche';

export const getDataManagementSettings = () => {
	return apiFetch({
		path: '/logtrail/v1/settings/data-management',
		method: 'GET',
		useApiCache: true,
	});
};

export const updateDataManagementSettings = (data) => {
	apiCache.clear('/logtrail/v1/settings/data-management');
	return apiFetch({
		path: '/logtrail/v1/settings/data-management',
		method: 'PUT',
		data,
	});
};

export const deleteOldDataInstantly = () => {
	return apiFetch({
		path: '/logtrail/v1/settings/data-management/delete-old-data',
		method: 'POST',
	});
};
