import React, { useMemo } from 'react';

import { applyFilters } from '@wordpress/hooks';

import {
	Card,
	Flex,
	Button,
} from '@framework/components';

import {
	InputSelect,
	InputSelectAsync,
	InputDate,
} from '@framework/components/form';

import {
	useForm,
	useWatch,
} from 'react-hook-form';

import { FiX } from 'react-icons/fi';

import { fetchLogFilterOptions } from '../../services/logsApi';

import './index.css';

const LogFilters = ({
	defaultValues = {},
	onApply = null,
	onReset = null,
	onClose = null,
}) => {
	const {
		control,
		handleSubmit,
		reset,
	} = useForm({
		defaultValues: {
			user_ids: [],
			event: [],
			severity: [],
			ids: [],
			date_range: 'all',
			date_from: '',
			date_to: '',
			...defaultValues,
		},
	});

	const dateRange = useWatch({
		control,
		name: 'date_range',
	});

	const dateRangeOptions = useMemo(
		() => [
			{ label: 'All', value: 'all' },
			{ label: 'Today', value: 'today' },
			{ label: 'Yesterday', value: 'yesterday' },
			{ label: 'Last 7 Days', value: 'last_7_days' },
			{ label: 'Last Week', value: 'last_week' },
			{ label: 'Last Month', value: 'last_month' },
			{ label: 'Last 30 Days', value: 'last_30_days' },
			{ label: 'Custom Range', value: 'custom_range' },
		],
		[]
	);

	const severityOptions = useMemo(
		() => [
			{ label: 'Info', value: 'info' },
			{ label: 'Success', value: 'success' },
			{ label: 'Warning', value: 'warning' },
			{ label: 'Error', value: 'error' },
		],
		[]
	);

	const normalizeSelectValues = (values = []) => (
		(values || []).map((item) => (
			item && typeof item === 'object' && item.value !== undefined
				? item.value
				: item
		))
	);

	const loadFilterOptions = async (type, keyword = '') => {
		try {
			return await fetchLogFilterOptions({
				type,
				search: keyword,
			});
		} catch (error) {
			return [];
		}
	};

	const loadUserOptions = (keyword) => loadFilterOptions('users', keyword);
	const loadEventOptions = (keyword) => loadFilterOptions('events', keyword);
	const loadIdOptions = (keyword) => loadFilterOptions('ids', keyword);

	const handleReset = () => {
		reset({
			user_ids: [],
			event: [],
			severity: [],
			ids: [],
			date_range: 'all',
			date_from: '',
			date_to: '',
		});

		if (onReset) {
			onReset();
		}
	};

	return (
		<Card className="wptl-log-filters">
			<form
				onSubmit={handleSubmit((values) => {
					const payload = {
						...values,
						user_ids: normalizeSelectValues(values.user_ids),
						event: normalizeSelectValues(values.event),
						ids: normalizeSelectValues(values.ids),
					};

					if (payload.date_range !== 'custom_range') {
						payload.date_from = '';
						payload.date_to = '';
					}

					if (onApply) {
						onApply(payload);
					}
				})}
			>
				<Flex vertical gap={20}>
					<div className="wptl-log-filters-header">
						<div className="wptl-log-filters-title">
							Advanced Filters
						</div>

						{onClose && (
							<button
								type="button"
								className="wptl-log-filters-close"
								onClick={onClose}
								aria-label="Close advanced filters"
							>
								<FiX />
							</button>
						)}
					</div>

					<Flex className="wptl-log-filters-grid" gap={15} wrap>
						<InputSelectAsync
							name="user_ids"
							control={control}
							label="User"
							placeholder="Search users..."
							loadOptions={loadUserOptions}
							defaultOptions={false}
							isMulti
						/>

						<InputSelectAsync
							name="event"
							control={control}
							label="Event"
							placeholder="Search events..."
							loadOptions={loadEventOptions}
							defaultOptions={false}
							isMulti
						/>

						<InputSelect
							name="severity"
							control={control}
							label="Severity"
							placeholder="Select severities..."
							options={severityOptions}
							isMulti
						/>

						<InputSelectAsync
							name="ids"
							control={control}
							label="ID"
							placeholder="Search IDs..."
							loadOptions={loadIdOptions}
							defaultOptions={false}
							isMulti
						/>

						<InputSelect
							name="date_range"
							control={control}
							label="Date"
							options={dateRangeOptions}
						/>
					</Flex>

					{dateRange === 'custom_range' && (
						<Flex className="wptl-log-filters-grid" gap={15} wrap>
							<InputDate
								name="date_from"
								control={control}
								label="From"
							/>

							<InputDate
								name="date_to"
								control={control}
								label="To"
							/>
						</Flex>
					)}

					{/*
					 * Extension point: add-ons (e.g. logtrail-pro) can inject
					 * additional filter fields (bound to the same react-hook-form
					 * `control`) by filtering this array.
					 */}
					<Flex className="wptl-log-filters-grid" gap={15} wrap>
						{applyFilters(
							'logtrail.activityLogs.filters.extraFields',
							[],
							{ control }
						)}
					</Flex>

					<Flex gap={10}>
						<Button
							type="primary"
							htmlType="submit"
						>
							Apply Filters
						</Button>

						<Button
							type="default"
							onClick={handleReset}
						>
							Reset
						</Button>

						{onClose && (
							<Button
								type="default"
								onClick={onClose}
							>
								Close
							</Button>
						)}
					</Flex>
				</Flex>
			</form>
		</Card>
	);
};

export default LogFilters;