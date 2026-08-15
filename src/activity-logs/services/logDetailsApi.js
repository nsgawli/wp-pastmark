import apiFetch from '@wordpress/api-fetch';

export const fetchLogDetails = async (logId) => {
	const response = await apiFetch({
		path: `/pastmark/v1/logs/${logId}`,
		method: 'GET',
	});

	return response?.data || null;
};