import React from 'react';

import './index.css';

const DashboardCard = ({
	title,
	value,
	subtitle = '',
	icon = null,
	tone = 'blue',
	variant = 'trend-up',
}) => {
	const renderVisual = () => {
		switch (variant) {
			case 'wave':
				return (
					<svg viewBox="0 0 160 120" focusable="false">
						<path
							d="M0 82 C20 70, 36 72, 52 80 C70 88, 88 88, 106 78 C124 68, 140 66, 160 74 L160 120 L0 120 Z"
							className="wppm-dashboard-card-visual-surface"
						/>
						<path
							d="M10 72 C28 60, 42 62, 56 70 C74 80, 92 78, 110 68 C126 60, 140 58, 152 62"
							className="wppm-dashboard-card-visual-line"
						/>
						<circle
							cx="152"
							cy="62"
							r="5"
							className="wppm-dashboard-card-visual-dot"
						/>
					</svg>
				);

			case 'blocks':
				return (
					<svg viewBox="0 0 160 120" focusable="false">
						<rect
							x="16"
							y="74"
							width="28"
							height="28"
							rx="8"
							className="wppm-dashboard-card-visual-bar"
						/>
						<rect
							x="50"
							y="56"
							width="28"
							height="46"
							rx="8"
							className="wppm-dashboard-card-visual-bar"
						/>
						<rect
							x="84"
							y="36"
							width="28"
							height="66"
							rx="8"
							className="wppm-dashboard-card-visual-bar"
						/>
						<rect
							x="118"
							y="22"
							width="26"
							height="80"
							rx="8"
							className="wppm-dashboard-card-visual-bar wppm-dashboard-card-visual-bar--strong"
						/>
					</svg>
				);

			case 'pulse':
				return (
					<svg viewBox="0 0 160 120" focusable="false">
						<path
							d="M0 72 H24 L40 48 L56 84 L70 60 L86 74 L100 38 L118 76 L132 64 H160"
							className="wppm-dashboard-card-visual-line"
						/>
						<circle
							cx="100"
							cy="38"
							r="5"
							className="wppm-dashboard-card-visual-dot"
						/>
						<circle
							cx="132"
							cy="64"
							r="5"
							className="wppm-dashboard-card-visual-dot"
						/>
					</svg>
				);

			case 'radial':
				return (
					<svg viewBox="0 0 160 120" focusable="false">
						<circle
							cx="82"
							cy="62"
							r="36"
							className="wppm-dashboard-card-visual-ring"
						/>
						<circle
							cx="82"
							cy="62"
							r="24"
							className="wppm-dashboard-card-visual-ring wppm-dashboard-card-visual-ring--inner"
						/>
						<path
							d="M82 26 A36 36 0 0 1 112 45"
							className="wppm-dashboard-card-visual-line"
						/>
						<circle
							cx="112"
							cy="45"
							r="5"
							className="wppm-dashboard-card-visual-dot"
						/>
					</svg>
				);

			case 'users':
				return (
					<svg viewBox="0 0 160 120" focusable="false">
						<circle
							cx="56"
							cy="44"
							r="12"
							className="wppm-dashboard-card-visual-bar"
						/>
						<circle
							cx="102"
							cy="40"
							r="14"
							className="wppm-dashboard-card-visual-bar wppm-dashboard-card-visual-bar--strong"
						/>
						<path
							d="M30 92 C30 76, 44 66, 60 66 C76 66, 90 76, 90 92"
							className="wppm-dashboard-card-visual-surface"
						/>
						<path
							d="M76 96 C76 76, 92 64, 112 64 C132 64, 148 76, 148 96"
							className="wppm-dashboard-card-visual-surface"
						/>
					</svg>
				);

			case 'trend-up':
			default:
				return (
					<svg viewBox="0 0 160 120" focusable="false">
						<rect
							x="18"
							y="72"
							width="16"
							height="28"
							rx="8"
							className="wppm-dashboard-card-visual-bar"
						/>
						<rect
							x="40"
							y="58"
							width="16"
							height="42"
							rx="8"
							className="wppm-dashboard-card-visual-bar"
						/>
						<rect
							x="62"
							y="44"
							width="16"
							height="56"
							rx="8"
							className="wppm-dashboard-card-visual-bar"
						/>
						<path
							d="M14 94 C40 75, 68 60, 92 56 C112 53, 130 58, 146 42"
							className="wppm-dashboard-card-visual-line"
						/>
						<circle
							cx="146"
							cy="42"
							r="6"
							className="wppm-dashboard-card-visual-dot"
						/>
					</svg>
				);
		}
	};

	return (
		<div className={`wppm-dashboard-card wppm-dashboard-card--${tone}`}>
			<div className="wppm-dashboard-card-content">
				<div className="wppm-dashboard-card-header">
					<div className="wppm-dashboard-card-title">{title}</div>

					{icon && (
						<div className="wppm-dashboard-card-icon">{icon}</div>
					)}
				</div>

				<div className="wppm-dashboard-card-value">{value}</div>

				{subtitle && (
					<div className="wppm-dashboard-card-subtitle">
						{subtitle}
					</div>
				)}
			</div>

			<div className="wppm-dashboard-card-visual" aria-hidden="true">
				{renderVisual()}
			</div>
		</div>
	);
};

export default DashboardCard;
