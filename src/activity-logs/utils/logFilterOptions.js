// Shared option lists for the advanced log filters. Kept in one place so the
// filter form (which options can be picked) and any place that displays an
// applied filter's value (e.g. the "Applied Filters" summary) always agree
// on the same label for a given value.

// Corrected 2026-08-28 (Sprint 9/PM-163 planning): this list had drifted
// from the real severity values `includes/Constants/Severity.php` actually
// stores/emits (`info`/`warning`/`error`/`critical`/`debug`) — it was
// missing `critical` and `debug` outright (so the Advanced Filters
// severity dropdown could never select either, even though the backend
// fully supports both) and offered a `success` value that was never a
// real severity and would never match a stored row. Found while building
// Pastmark Pro's Alerts rule builder, which needed an accurate severity
// list to reuse rather than replicate the same drift into a second place.
export const SEVERITY_OPTIONS = [
	{ label: 'Info', value: 'info' },
	{ label: 'Warning', value: 'warning' },
	{ label: 'Error', value: 'error' },
	{ label: 'Critical', value: 'critical' },
	{ label: 'Debug', value: 'debug' },
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

// `ai_agent` is listed even though the detection logic behind it
// (`AbstractLogger::detect_actor_type()`) lands in a separate ticket this
// same sprint — the filter option needs to exist now so it isn't a second
// follow-up change once that value actually starts appearing on rows.
export const ACTOR_TYPE_OPTIONS = [
	{ label: 'Human', value: 'human' },
	{ label: 'System', value: 'system' },
	{ label: 'Scheduled', value: 'scheduled' },
	{ label: 'AI Agent', value: 'ai_agent' },
];

// Corrected 2026-08-28 (Sprint 9/PM-163 planning): never updated when
// Sprint 8 shipped the ACF/WPForms/Gravity Forms integrations — their
// `integration` values (`acf`/`wpforms`/`gravityforms`, see each
// integration's `AbstractLogger::$integration`) were filterable via the
// REST API from day one but never selectable in this dropdown. Same class
// of "shipped feature never propagated to a dependent list" gap as the
// severity correction above.
export const INTEGRATION_OPTIONS = [
	{ label: 'Core', value: 'core' },
	{ label: 'WooCommerce', value: 'woocommerce' },
	{ label: 'ACF', value: 'acf' },
	{ label: 'WPForms', value: 'wpforms' },
	{ label: 'Gravity Forms', value: 'gravityforms' },
];

const optionsToLabelMap = (options) => (
	options.reduce((acc, option) => {
		acc[option.value] = option.label;
		return acc;
	}, {})
);

export const SEVERITY_LABELS = optionsToLabelMap(SEVERITY_OPTIONS);

export const DATE_RANGE_LABELS = optionsToLabelMap(DATE_RANGE_OPTIONS);

export const ACTOR_TYPE_LABELS = optionsToLabelMap(ACTOR_TYPE_OPTIONS);

export const INTEGRATION_LABELS = optionsToLabelMap(INTEGRATION_OPTIONS);
