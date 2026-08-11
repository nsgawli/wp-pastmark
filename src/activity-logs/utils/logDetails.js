export const LOG_DETAILS_VIEW_MODES = {
	drawer: 'drawer',
	singlePage: 'single_page',
};

export const buildLogDetailsPath = (logId) => `/log/${logId}`;

export const buildLogDetailsUrl = (logId) => {
	if (!logId) {
		return `${window.location.origin}${window.location.pathname}?page=logtrail`;
	}

	return `${window.location.origin}${window.location.pathname}?page=logtrail#${buildLogDetailsPath(logId)}`;
};

export const getLogMarkdown = (log = {}) => {
	return `**${log.event || '-'}** - ${log.message || '-'}\n\n- **User:** ${log.user || '-'}\n- **IP:** ${log.ip || '-'}\n- **Timestamp:** ${log.date || '-'}`;
};

export const getLogJson = (log = {}) => JSON.stringify(log, null, 2);