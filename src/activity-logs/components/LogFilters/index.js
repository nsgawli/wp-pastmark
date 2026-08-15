import React from 'react';

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

import {
	SEVERITY_OPTIONS,
	DATE_RANGE_OPTIONS,
} from '../../utils/logFilterOptions';

import './index.css';

// Number of options to show for an autocomplete field before the user has
// typed anything (e.g. the first click into the User/Event fields).
const DEFAULT_OPTIONS_PREVIEW_COUNT = 5;

// `user_ids`/`event`/`ids` are expected as already-labelled `{ value, label
// }` pairs (the caller resolves labels — e.g. from applied filter state —
// before opening this panel, so a re-opened field shows a name instead of a
// blank tag). This normalizes any plain value that slips through anyway
// (e.g. a fresh filter that was never labelled) so the field never renders a
// tag with no text at all.
const toSelectOption = (item) => (
	item && typeof item === 'object' && item.value !== undefined
		? item
		: { value: item, label: String(item) }
);

const normalizeSelectDefaultValues = (values = []) => (
	Array.isArray(values) ? values.map(toSelectOption) : []
);

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
			severity: [],
			date_range: 'all',
			date_from: '',
			date_to: '',
			...defaultValues,
			user_ids: normalizeSelectDefaultValues(defaultValues.user_ids),
			event: normalizeSelectDefaultValues(defaultValues.event),
			ids: normalizeSelectDefaultValues(defaultValues.ids),
		},
	});

	const dateRange = useWatch({
		control,
		name: 'date_range',
	});

	const dateFrom = useWatch({
		control,
		name: 'date_from',
	});

	const dateTo = useWatch({
		control,
		name: 'date_to',
	});

	const normalizeSelectValues = (values = []) => (
		(values || []).map((item) => (
			item && typeof item === 'object' && item.value !== undefined
				? item.value
				: item
		))
	);

	const loadFilterOptions = async (type, keyword = '', limit = null) => {
		try {
			return await fetchLogFilterOptions({
				type,
				search: keyword,
				limit,
			});
		} catch (error) {
			return [];
		}
	};

	// Before the user types anything, show a short preview list (e.g. the
	// first 5 users/events) instead of an empty "type to search" field. Once
	// a keyword is entered, fall back to the full matching result set.
	const loadUserOptions = (keyword) =>
		loadFilterOptions(
			'users',
			keyword,
			keyword ? null : DEFAULT_OPTIONS_PREVIEW_COUNT
		);

	const loadEventOptions = (keyword) =>
		loadFilterOptions(
			'events',
			keyword,
			keyword ? null : DEFAULT_OPTIONS_PREVIEW_COUNT
		);

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
		<Card className="wppm-log-filters">
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
					<div className="wppm-log-filters-header">
						<div className="wppm-log-filters-title">
							Advanced Filters
						</div>

						{onClose && (
							<button
								type="button"
								className="wppm-log-filters-close"
								onClick={onClose}
								aria-label="Close advanced filters"
							>
								<FiX />
							</button>
						)}
					</div>

					<Flex className="wppm-log-filters-grid" gap={15} wrap>
						<InputSelectAsync
							name="user_ids"
							control={control}
							label="User"
							placeholder="Search users..."
							loadOptions={loadUserOptions}
							defaultOptions
							isMulti
						/>

						<InputSelectAsync
							name="event"
							control={control}
							label="Event"
							placeholder="Search events..."
							loadOptions={loadEventOptions}
							defaultOptions
							isMulti
						/>

						<InputSelect
							name="severity"
							control={control}
							label="Severity"
							placeholder="Select severities..."
							options={SEVERITY_OPTIONS}
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
							options={DATE_RANGE_OPTIONS}
						/>
					</Flex>

					{dateRange === 'custom_range' && (
						<Flex className="wppm-log-filters-grid" gap={15} wrap>
							<InputDate
								name="date_from"
								control={control}
								label="From"
								placeholderText="From date"
								maxDate={dateTo || null}
							/>

							<InputDate
								name="date_to"
								control={control}
								label="To"
								placeholderText="To date"
								minDate={dateFrom || null}
							/>
						</Flex>
					)}

					{/*
					 * Extension point: add-ons (e.g. pastmark-pro) can inject
					 * additional filter fields (bound to the same react-hook-form
					 * `control`) by filtering this array.
					 */}
					<Flex className="wppm-log-filters-grid" gap={15} wrap>
						{applyFilters(
							'pastmark.activityLogs.filters.extraFields',
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