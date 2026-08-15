import React from 'react';
import './index.css';

const Card = ({
	children,
	className = '',
	style = {},
	padding = true,
	clickable = false,
	onClick = null,
}) => {
	return (
		<div
			className={`
				wppm-card
				${padding ? 'wppm-card-padding' : ''}
				${clickable ? 'wppm-card-clickable' : ''}
				${className}
			`}
			style={style}
			onClick={onClick}
		>
			{children}
		</div>
	);
};

export default Card;
