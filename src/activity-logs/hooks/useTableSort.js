import { useState } from 'react';

const useTableSort = () => {
	const [sortBy, setSortBy] = useState('id');
	const [sortOrder, setSortOrder] = useState('DESC');

	const handleSort = (column) => {
		if (sortBy === column) {
			setSortOrder(
				sortOrder === 'ASC'
					? 'DESC'
					: 'ASC'
			);

			return;
		}

		setSortBy(column);
		setSortOrder('ASC');
	};

	return {
		sortBy,
		sortOrder,
		handleSort,
	};
};

export default useTableSort;