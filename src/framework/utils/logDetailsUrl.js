// Shared by the Activity Logs app and the Dashboard widgets: both need to
// link to a specific log's details page, but each is built as its own
// webpack entry/admin page, so the URL has to be built from scratch rather
// than assuming the current location is already the Activity Logs page.
export const buildLogDetailsPath = (logId) => `/log/${logId}`;

export const buildLogDetailsUrl = (logId) => {
	if (!logId) {
		return `${window.location.origin}${window.location.pathname}?page=pastmark`;
	}

	return `${window.location.origin}${window.location.pathname}?page=pastmark#${buildLogDetailsPath(logId)}`;
};
