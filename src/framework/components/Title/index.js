import React from 'react';
import './index.css';

const Title = React.memo(({ level, style = {}, children }) => {
	const TitleTag = `h${level}`;
	return (
		<TitleTag className="psm-title" style={style}>
			{children}
		</TitleTag>
	);
});

export default Title;
