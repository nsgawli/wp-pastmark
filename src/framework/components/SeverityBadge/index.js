import React from 'react';

import { Badge } from '@framework/components';

const SeverityBadge = ({ severity = 'info' }) => {
	const severityMap = {
		success: {
			type: 'success',
			label: 'Success',
		},
		info: {
			type: 'info',
			label: 'Info',
		},
		warning: {
			type: 'warning',
			label: 'Warning',
		},
		error: {
			type: 'error',
			label: 'Error',
		},
		critical: {
			type: 'error',
			label: 'Critical',
		},
		debug: {
			type: 'info',
			label: 'Debug',
		},
	};

	const config = severityMap[severity] || severityMap.info;

	return (
		<Badge type={config.type}>
			{config.label}
		</Badge>
	);
};

export default SeverityBadge;