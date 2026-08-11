import React from 'react';

import {
	Flex,
	Button,
} from '@framework/components';

import './index.css';

const Pagination = ({
	current = 1,
	total = 1,
	totalItems = 0,
	perPage = 20,
	label = 'Logs',
	onChange = null,
}) => {
	const startItem =
		totalItems === 0
			? 0
			: ((current - 1) * perPage) + 1;

	const endItem = Math.min(
		current * perPage,
		totalItems
	);

	const goToFirstPage = () => {
		if (current > 1 && onChange) {
			onChange(1);
		}
	};

	const goToPreviousPage = () => {
		if (current > 1 && onChange) {
			onChange(current - 1);
		}
	};

	const goToNextPage = () => {
		if (current < total && onChange) {
			onChange(current + 1);
		}
	};

	const goToLastPage = () => {
		if (current < total && onChange) {
			onChange(total);
		}
	};

	return (
		<Flex
			className="wptl-pagination"
			justify="flex-end"
			align="center"
			gap={10}
			wrap
		>
			<Button
				size="small"
				disabled={current === 1}
				onClick={goToFirstPage}
			>
				First Page
			</Button>

			<Button
				size="small"
				disabled={current === 1}
				onClick={goToPreviousPage}
			>
				Prev
			</Button>

			<div className="wptl-pagination-info">
				{startItem} - {endItem} of {totalItems} {label}
			</div>

			<Button
				size="small"
				disabled={current === total}
				onClick={goToNextPage}
			>
				Next
			</Button>

			<Button
				size="small"
				disabled={current === total}
				onClick={goToLastPage}
			>
				Last Page
			</Button>
		</Flex>
	);
};

export default Pagination;