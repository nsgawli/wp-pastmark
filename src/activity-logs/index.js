import React from 'react';
import ReactDOM from 'react-dom';
import { ScreenSizeProvider } from '@framework/hooks/useScreenSize';
import { AlertsProvider } from '@framework/hooks/useAlerts';
import { AlertContainer } from '@framework/components';
import App from './App';
import { HashRouter as Router } from 'react-router-dom';

document.addEventListener('DOMContentLoaded', () => {
	const element = document.getElementById('logtrail-activity-logs');
	ReactDOM.createRoot(element).render(
		<Router>
			<ScreenSizeProvider element={element}>
				<AlertsProvider>
					<App />
					<AlertContainer />
				</AlertsProvider>
			</ScreenSizeProvider>
		</Router>
	);
});
