// Row density (Default / Compact) for the logs page — a pure per-browser
// preference, unlike `logsPageViewMode` (table/timeline) which is a synced
// server-side setting. Each user's browser remembers its own choice via a
// cookie, following the same read/write pattern as the filters cookie in
// `hooks/useLogs.js`.

export const LOGS_ROW_DENSITY = {
	default: 'default',
	compact: 'compact',
};

const DENSITY_COOKIE_NAME = 'pastmark_activity_log_density';
const DENSITY_COOKIE_MAX_AGE_SECONDS = 60 * 60 * 24 * 365;

export const readRowDensityFromCookie = () => {
	if (typeof document === 'undefined') {
		return LOGS_ROW_DENSITY.default;
	}

	const allCookies = document.cookie ? document.cookie.split('; ') : [];
	const cookieValue = allCookies.find((cookie) => (
		cookie.startsWith(`${DENSITY_COOKIE_NAME}=`)
	));

	if (!cookieValue) {
		return LOGS_ROW_DENSITY.default;
	}

	const value = decodeURIComponent(
		cookieValue.substring(DENSITY_COOKIE_NAME.length + 1)
	);

	return value === LOGS_ROW_DENSITY.compact
		? LOGS_ROW_DENSITY.compact
		: LOGS_ROW_DENSITY.default;
};

export const writeRowDensityToCookie = (density) => {
	if (typeof document === 'undefined') {
		return;
	}

	const value = density === LOGS_ROW_DENSITY.compact
		? LOGS_ROW_DENSITY.compact
		: LOGS_ROW_DENSITY.default;

	document.cookie = `${DENSITY_COOKIE_NAME}=${value}; path=/; max-age=${DENSITY_COOKIE_MAX_AGE_SECONDS}; samesite=lax`;
};
