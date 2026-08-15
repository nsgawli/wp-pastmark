import apiFetch from '@wordpress/api-fetch';
import apiCache from '@framework/middlewares/apiCatche';

export const getEmailReportsSettings = () => {
	return apiFetch({
		path: '/pastmark/v1/settings/email-reports',
		method: 'GET',
		useApiCache: true,
	});
};

export const updateEmailReportsSettings = (data) => {
	apiCache.clear('/pastmark/v1/settings/email-reports');
	return apiFetch({
		path: '/pastmark/v1/settings/email-reports',
		method: 'PUT',
		data,
	});
};

export const resetEmailReportsSettings = () => {
	return apiFetch({
		path: '/pastmark/v1/settings/email-reports/defaults',
		method: 'GET',
		useApiCache: true,
	});
};

export const sendEmailReportTest = (reportType, recipients) => {
	return apiFetch({
		path: '/pastmark/v1/settings/email-reports/send-test',
		method: 'POST',
		data: {
			reportType,
			recipients,
		},
	});
};
