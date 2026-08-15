import React from 'react';

import { Flex, Button, SearchInput } from '@framework/components';

import { FiRefreshCw, FiFilter } from 'react-icons/fi';

import './index.css';

const LogToolbar = ({
	search = '',
	isRefreshing = false,
	onSearch = null,
	onRefresh = null,
	onToggleFilters = null,
	actions = [],
}) => {
	return (
		<Flex
			className="wppm-log-toolbar"
			justify="space-between"
			align="center"
			wrap
			gap={15}
		>
			<SearchInput
				value={search}
				onChange={onSearch}
				placeholder="Search logs..."
			/>

			<Flex className="wppm-log-toolbar-actions" gap={10} wrap>
				<Button
					size="small"
					icon={<FiFilter />}
					onClick={onToggleFilters}
				>
					Filters
				</Button>

				<Button
					size="small"
					icon={<FiRefreshCw />}
					loading={isRefreshing}
					onClick={onRefresh}
				>
					Refresh
				</Button>

				{actions.map((action) => (
					<Button
						key={action.key}
						type={action.type || 'default'}
						size="small"
						icon={action.icon}
						loading={action.loading}
						onClick={action.onClick}
					>
						{action.label}
					</Button>
				))}
			</Flex>
		</Flex>
	);
};

export default LogToolbar;
