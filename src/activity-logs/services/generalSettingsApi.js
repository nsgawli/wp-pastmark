import apiFetch from '@wordpress/api-fetch';

export const fetchGeneralSettings = async () => {
	const response = await apiFetch({
		path: '/logtrail/v1/settings/general-settings',
		method: 'GET',
	});

	return response?.data || {};
};
