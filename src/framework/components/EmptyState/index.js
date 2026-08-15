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
			className="wppm-empty-state"
			vertical
			align="center"
			gap={15}
		>
			{icon && (
				<div className="wppm-empty-state-icon">
					{icon}
				</div>
			)}

			<div className="wppm-empty-state-title">
				{title}
			</div>

			{description && (
				<div className="wppm-empty-state-description">
					{description}
				</div>
			)}

			{action}
		</Flex>
	);
};

export default EmptyState;