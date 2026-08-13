// Shared option lists for the advanced log filters. Kept in one place so the
// filter form (which options can be picked) and any place that displays an
// applied filter's value (e.g. the "Applied Filters" summary) always agree
// on the same label for a given value.

export const SEVERITY_OPTIONS = [
	{ label: 'Info', value: 'info' },
	{ label: 'Success', value: 'success' },
	{ label: 'Warning', value: 'warning' },
	{ label: 'Error', value: 'error' },
];

export const DATE_RANGE_OPTIONS = [
	{ label: 'All', value: 'all' },
	{ label: 'Today', value: 'today' },
	{ label: 'Yesterday', value: 'yesterday' },
	{ label: 'Last 7 Days', value: 'last_7_days' },
	{ label: 'Last Week', value: 'last_week' },
	{ label: 'Last Month', value: 'last_month' },
	{ label: 'Last 30 Days', value: 'last_30_days' },
	{ label: 'Custom Range', value: 'custom_range' },
];

const optionsToLabelMap = (options) => (
	options.reduce((acc, option) => {
		acc[option.value] = option.label;
		return acc;
	}, {})
);

export const SEVERITY_LABELS = optionsToLabelMap(SEVERITY_OPTIONS);

export const DATE_RANGE_LABELS = optionsToLabelMap(DATE_RANGE_OPTIONS);
