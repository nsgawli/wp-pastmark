import React from 'react';

const ProductIcon = ({ className = '', style = {} }) => {
	return (
		<svg
			className={className}
			style={style}
			xmlns="http://www.w3.org/2000/svg"
			viewBox="0 0 512 512"
		>
			<defs>
				<linearGradient id="bg" x1="0" y1="0" x2="1" y2="1">
					<stop offset="0%" stopColor="#3730A3" />
					<stop offset="100%" stopColor="#4F46E5" />
				</linearGradient>
				<filter
					id="shadow"
					x="-20%"
					y="-20%"
					width="140%"
					height="140%"
				>
					<feDropShadow
						dx="0"
						dy="6"
						stdDeviation="8"
						floodOpacity=".18"
					/>
				</filter>
			</defs>

			<circle
				cx="256"
				cy="256"
				r="248"
				fill="url(#bg)"
				filter="url(#shadow)"
			/>

			<text
				x="256"
				y="288"
				textAnchor="middle"
				fontFamily="Arial, Helvetica, sans-serif"
				fontSize="170"
				fontWeight="800"
				letterSpacing="-6"
				fill="#fff"
			>
				LT
			</text>

			<line
				x1="150"
				y1="356"
				x2="362"
				y2="356"
				stroke="#fff"
				strokeWidth="9"
				strokeLinecap="round"
				opacity="0.55"
			/>
			<circle cx="150" cy="356" r="12" fill="#fff" />
			<circle cx="256" cy="356" r="14" fill="#fff" />
			<circle cx="362" cy="356" r="12" fill="#fff" />
		</svg>
	);
};

export default ProductIcon;
