import apiFetch from '@wordpress/api-fetch';

export const getDashboard = (range = '30days') => {
	return apiFetch({
		path: `/logtrail/v1/dashboard?range=${range}`,
	});
};
