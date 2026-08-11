import React from 'react';

import {
	Flex,
	Button,
	Badge,
} from '@framework/components';

import './index.css';

const SavedFilters = ({
	items = [],
	onApply = null,
	onDelete = null,
}) => {
	if (!items.length) {
		return null;
	}

	return (
		<Flex
			className="wptl-saved-filters"
			gap={10}
			wrap
		>
			{items.map((item) => (
				<Flex
					key={item.id}
					gap={5}
					align="center"
				>
					<Button
						size="small"
						onClick={() => {
							onApply(item);
						}}
					>
						{item.name}
					</Button>

					<Badge
						type="danger"
						onClick={() => {
							onDelete(item.id);
						}}
					>
						×
					</Badge>
				</Flex>
			))}
		</Flex>
	);
};

export default SavedFilters;