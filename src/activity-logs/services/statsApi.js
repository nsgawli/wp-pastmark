import apiFetch from '@wordpress/api-fetch';

export const fetchStats = async () => {
	const response = await apiFetch({
		path: '/logtrail/v1/logs/stats',
		method: 'GET',
	});

	return response?.data || {};
};