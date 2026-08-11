import React from 'react';
import { Flex } from '@framework/components';
import './index.css';

const EmptyState = ({
	title = 'No Data Found',
	description = '',
	icon = null,
	action = null,
}) => {
	return (
		<Flex
			className="wptl-empty-state"
			vertical
			align="center"
			gap={15}
		>
			{icon && (
				<div className="wptl-empty-state-icon">
					{icon}
				</div>
			)}

			<div className="wptl-empty-state-title">
				{title}
			</div>

			{description && (
				<div className="wptl-empty-state-description">
					{description}
				</div>
			)}

			{action}
		</Flex>
	);
};

export default EmptyState;