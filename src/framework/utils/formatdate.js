import { __ } from '@wordpress/i18n';
import { format } from 'date-fns';
import enUS from 'date-fns/locale/en-US';

export const formatDate = (date) => {
	return format(date, 'yyyy-MM-dd');
};

export const formatDateTime = (date) => {
	return format(date, 'yyyy-MM-dd HH:mm:ss');
};

export const formatTime = (date) => {
	return format(date, 'HH:mm');
};

export const customLocale = {
	...enUS,
	localize: {
		...enUS.localize,
		month: (n) =>
			[
				__('January', 'psmwraq'),
				__('February', 'psmwraq'),
				__('March', 'psmwraq'),
				__('April', 'psmwraq'),
				__('May', 'psmwraq'),
				__('June', 'psmwraq'),
				__('July', 'psmwraq'),
				__('August', 'psmwraq'),
				__('September', 'psmwraq'),
				__('October', 'psmwraq'),
				__('November', 'psmwraq'),
				__('December', 'psmwraq'),
			][n],
		day: (n) =>
			[
				__('Sun', 'psmwraq'),
				__('Mon', 'psmwraq'),
				__('Tue', 'psmwraq'),
				__('Wed', 'psmwraq'),
				__('Thu', 'psmwraq'),
				__('Fri', 'psmwraq'),
				__('Sat', 'psmwraq'),
			][n],
		dayPeriod: (period) =>
			({
				am: __('AM', 'psmwraq'),
				pm: __('PM', 'psmwraq'),
			})[period],
	},
};
