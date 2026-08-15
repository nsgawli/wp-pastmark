import apiFetch from '@wordpress/api-fetch';

export const getDashboard = (range = '30days') => {
	return apiFetch({
		path: `/pastmark/v1/dashboard?range=${range}`,
	});
};
