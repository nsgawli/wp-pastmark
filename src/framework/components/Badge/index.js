import React from 'react';
import './index.css';

const Badge = ({
	type = 'default',
	children,
	className = '',
}) => {
	return (
		<span className={`wppm-badge wppm-badge-${type} ${className}`}>
			{children}
		</span>
	);
};

export default Badge;