import { useState } from 'react';

const STORAGE_KEY = 'pastmark_saved_filters';

const useSavedFilters = () => {
	const [savedFilters, setSavedFilters] = useState(() => {
		try {
			return JSON.parse(
				localStorage.getItem(STORAGE_KEY)
			) || [];
		} catch (e) {
			return [];
		}
	});

	const saveFilter = (name, filters) => {
		const next = [
			...savedFilters,
			{
				id: Date.now(),
				name,
				filters,
			},
		];

		localStorage.setItem(
			STORAGE_KEY,
			JSON.stringify(next)
		);

		setSavedFilters(next);
	};

	const deleteFilter = (id) => {
		const next = savedFilters.filter(
			(filter) => filter.id !== id
		);

		localStorage.setItem(
			STORAGE_KEY,
			JSON.stringify(next)
		);

		setSavedFilters(next);
	};

	return {
		savedFilters,
		saveFilter,
		deleteFilter,
	};
};

export default useSavedFilters;