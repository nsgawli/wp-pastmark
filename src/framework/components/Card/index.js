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
				wptl-card
				${padding ? 'wptl-card-padding' : ''}
				${clickable ? 'wptl-card-clickable' : ''}
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
