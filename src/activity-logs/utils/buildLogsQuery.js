const buildLogsQuery = ({
	page = 1,
	perPage = 20,
	search = '',
	filters = {},
	sortBy = 'id',
	sortOrder = 'DESC',
	includePagination = true,
} = {}) => {
	const query = includePagination
		? {
				page,
				per_page: perPage,
				orderby: sortBy,
				order: sortOrder,
			}
		: {
				orderby: sortBy,
				order: sortOrder,
			};

	if (search) {
		query.search = search;
	}

	Object.entries(filters).forEach(([key, value]) => {
		if (value === '' || value === null || typeof value === 'undefined') {
			return;
		}

		if (Array.isArray(value)) {
			if (value.length === 0) {
				return;
			}

			query[key] = value.join(',');

			return;
		}

		query[key] = value;
	});

	return query;
};

export default buildLogsQuery;