import React, { useState } from 'react';
import './index.css';

const Tooltip = ({ children, content }) => {
	const [visible, setVisible] = useState(false);

	const showTooltip = () => setVisible(true);
	const hideTooltip = () => setVisible(false);

	return (
		<div
			className="psm-tooltip-container"
			onMouseEnter={showTooltip}
			onMouseLeave={hideTooltip}
		>
			{children}
			{visible && <div className="psm-tooltip-content">{content}</div>}
		</div>
	);
};

export default Tooltip;
