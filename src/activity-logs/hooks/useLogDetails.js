import { useCallback, useState } from 'react';

import apiFetch from '@wordpress/api-fetch';

const useLogDetails = () => {
	const [loading, setLoading] = useState(false);

	const [log, setLog] = useState(null);

	const loadLog = useCallback(async (logId) => {
		setLoading(true);

		try {
			const response = await apiFetch({
				path: `/pastmark/v1/logs/${logId}`,
			});

			setLog(response?.data || null);
		} finally {
			setLoading(false);
		}
	}, []);

	const clearLog = useCallback(() => {
		setLog(null);
	}, []);

	return {
		log,
		loading,
		loadLog,
		clearLog,
	};
};

export default useLogDetails;