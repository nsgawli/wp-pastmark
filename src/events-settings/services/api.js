import apiFetch from '@wordpress/api-fetch';

const EVENTS_API = '/pastmark/v1/settings/events';

export const getEventSettings = () => {
	return apiFetch({
		path: EVENTS_API,
	});
};

export const saveEventSettings = (settings, logLevel) => {
	return apiFetch({
		path: EVENTS_API,
		method: 'PUT',
		data: {
			settings,
			logLevel,
		},
	});
};
