import React from 'react';
import './index.css';

const EventSwitch = ({ checked = false, onChange = null }) => {
	return (
		<label className="psm-switch">
			<input
				type="checkbox"
				checked={checked}
				onChange={(event) => onChange(event.target.checked)}
			/>

			<span className="psm-switch-slider"></span>
		</label>
	);
};

export default EventSwitch;
