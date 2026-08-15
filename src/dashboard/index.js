import React from 'react';
import ReactDOM from 'react-dom';
import { ScreenSizeProvider } from '@framework/hooks/useScreenSize';
import { AlertsProvider } from '@framework/hooks/useAlerts';
import App from './App';
import { HashRouter as Router } from 'react-router-dom';

import './index.css';

document.addEventListener('DOMContentLoaded', () => {
	const element = document.getElementById('pastmark-dashboard');
	ReactDOM.createRoot(element).render(
		<Router>
			<ScreenSizeProvider element={element}>
				<AlertsProvider>
					<App />
				</AlertsProvider>
			</ScreenSizeProvider>
		</Router>
	);
});
