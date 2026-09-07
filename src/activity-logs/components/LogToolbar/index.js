import React from 'react';

import { Flex, Button, SearchInput } from '@framework/components';

import { FiRefreshCw, FiFilter } from 'react-icons/fi';

import NewEventsBanner from '../NewEventsBanner';

import './index.css';

const LogToolbar = ({
	search = '',
	isRefreshing = false,
	newEventsCount = 0,
	rowDensity = 'default',
	onSearch = null,
	onRefresh = null,
	onToggleFilters = null,
	onNewEventsClick = null,
	onRowDensityChange = null,
	actions = [],
}) => {
	return (
		<div className="wppm-log-toolbar">
			<div className="wppm-log-toolbar-search">
				<SearchInput
					value={search}
					onChange={onSearch}
					placeholder="Search logs..."
				/>
			</div>

			<div className="wppm-log-toolbar-center">
				<NewEventsBanner
					count={newEventsCount}
					onClick={onNewEventsClick}
				/>
			</div>

			<Flex className="wppm-log-toolbar-actions" gap={10} wrap>
				{onRowDensityChange && (
					<div
						className="wppm-log-density-toggle"
						role="group"
						aria-label="Row density"
					>
						<button
							type="button"
							className={`wppm-log-density-option${
								rowDensity !== 'compact'
									? ' wppm-log-density-option-active'
									: ''
							}`}
							onClick={() => onRowDensityChange('default')}
						>
							Default
						</button>

						<button
							type="button"
							className={`wppm-log-density-option${
								rowDensity === 'compact'
									? ' wppm-log-density-option-active'
									: ''
							}`}
							onClick={() => onRowDensityChange('compact')}
						>
							Compact
						</button>
					</div>
				)}

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
		</div>
	);
};

export default LogToolbar;
