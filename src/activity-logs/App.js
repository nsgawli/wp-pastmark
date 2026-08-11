import React from 'react';
import {
	Navigate,
	Route,
	Routes,
} from 'react-router-dom';

import LogsPage from './pages/LogsPage';
import LogDetailsPage from './pages/LogDetailsPage';

const App = () => {
	return (
		<Routes>
			<Route path="/" element={<LogsPage />} />
			<Route path="/log/:logId" element={<LogDetailsPage />} />
			<Route path="*" element={<Navigate to="/" replace />} />
		</Routes>
	);
};

export default App;