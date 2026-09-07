import React from 'react';

import { FiRefreshCw } from 'react-icons/fi';

import './index.css';

// Sits directly above the log list — shared by both the table and timeline
// views (`LogsPage` renders it once, above whichever of the two is active)
// — and disappears entirely while there's nothing new to report.
const NewEventsBanner = ({ count = 0, onClick = null }) => {
	if (!count) {
		return null;
	}

	const label = count === 1 ? '1 new event' : `${count} new events`;

	return (
		<button
			type="button"
			className="wppm-new-events-banner"
			onClick={onClick}
		>
			<FiRefreshCw className="wppm-new-events-banner-icon" />
			{label}
		</button>
	);
};

export default NewEventsBanner;
