import React, { useEffect, useState } from 'react';
import { __ } from '@wordpress/i18n';
import { useForm } from 'react-hook-form';

import {
	Flex,
	Content,
	Title,
	Button,
	ScreenLoader,
	Divider,
} from '@framework/components';

import {
	InputSelect,
	Textarea,
	InputSelectAsync,
	Switch,
} from '@framework/components/form';

import {
	getExcludeSettings,
	updateExcludeSettings,
	resetExcludeSettings,
	getExcludeOptions,
	loadUsers,
	loadPlugins,
	loadThemes,
} from './resource';

import { useAlerts } from '@framework/hooks/useAlerts';

// Older saved settings store plain values (e.g. a plugin file path or theme
// slug) instead of the { value, label } shape react-select needs. Fall back
// to the raw value as the label so a legacy selection still renders instead
// of blank.
const normalizeMultiSelectValue = (value) =>
	(value || []).map((item) =>
		item && typeof item === 'object'
			? item
			: { value: item, label: item }
	);

const ExcludeSettings = () => {
	const [isLoading, setIsLoading] = useState(false);
	const [isResetting, setIsResetting] = useState(false);

	const { handleSubmit, control, reset, formState } = useForm();

	const [options, setOptions] = useState({
		roles: [],
		postTypes: [],
		statuses: [],
	});

	const { addAlert } = useAlerts();

	useEffect(() => {
		const fetchSettings = async () => {
			setIsLoading(true);

			try {
				const [settingsResponse, optionsResponse] = await Promise.all([
					getExcludeSettings(),
					getExcludeOptions(),
				]);

				const settings = settingsResponse?.data ?? settingsResponse;

				const settingOptions = optionsResponse?.data ?? optionsResponse;

				setOptions(settingOptions);

				reset({
					...settings,
					excludeCronRequests: !!settings.excludeCronRequests,
					excludedUsers: settings.excludedUsers || [],
					excludedIPs: (settings.excludedIPs || []).join('\n'),
					excludedPostMeta: (settings.excludedPostMeta || []).join(
						'\n'
					),
					excludedUserMeta: (settings.excludedUserMeta || []).join(
						'\n'
					),
					excludedPlugins: normalizeMultiSelectValue(
						settings.excludedPlugins
					),
					excludedThemes: normalizeMultiSelectValue(
						settings.excludedThemes
					),
				});
			} catch (error) {
				console.error(error);
			} finally {
				setIsLoading(false);
			}
		};

		fetchSettings();
	}, []);

	const onSubmit = async (data) => {
		const payload = {
			...data,
			excludeCronRequests: !!data.excludeCronRequests,
			excludedUsers: data.excludedUsers || [],
			excludedIPs: data.excludedIPs
				? data.excludedIPs
						.split('\n')
						.map((item) => item.trim())
						.filter(Boolean)
				: [],
			excludedPostMeta: data.excludedPostMeta
				? data.excludedPostMeta
						.split('\n')
						.map((item) => item.trim())
						.filter(Boolean)
				: [],
			excludedUserMeta: data.excludedUserMeta
				? data.excludedUserMeta
						.split('\n')
						.map((item) => item.trim())
						.filter(Boolean)
				: [],
			excludedPlugins: data.excludedPlugins || [],
			excludedThemes: data.excludedThemes || [],
		};

		await updateExcludeSettings(payload)
			.then(() => {
				addAlert({
					id: Date.now(),
					type: 'success',
					title: __('Success', 'logtrail'),
					description: __(
						'Exclude settings saved successfully.',
						'logtrail'
					),
				});
			})
			.catch(() => {
				addAlert({
					id: Date.now(),
					type: 'error',
					title: __('Error', 'logtrail'),
					description: __(
						'Unable to save exclude settings.',
						'logtrail'
					),
				});
			});
	};

	const onReset = async () => {
		setIsResetting(true);

		await resetExcludeSettings()
			.then(async (response) => {
				const defaults = response?.data ?? response;

				await updateExcludeSettings(defaults);

				reset({
					...defaults,
					excludeCronRequests: !!defaults.excludeCronRequests,
					excludedIPs: '',
					excludedPostMeta: '',
					excludedUserMeta: '',
					excludedPlugins: [],
					excludedThemes: [],
				});

				addAlert({
					id: Date.now(),
					type: 'success',
					title: __('Success', 'logtrail'),
					description: __(
						'Exclude settings reset successfully.',
						'logtrail'
					),
				});
			})
			.finally(() => {
				setIsResetting(false);
			});
	};

	return (
		<Flex
			vertical
			gap={10}
			style={{
				flexGrow: 1,
				minWidth: 0,
				maxWidth: '800px',
			}}
		>
			<Content>
				{isLoading && <ScreenLoader />}

				{!isLoading && (
					<Flex vertical gap={10}>
						<Flex vertical gap={5}>
							<Title level={3}>
								{__('Exclude Settings', 'logtrail')}
							</Title>

							<span className="psm-setting-info">
								{__(
									'Exclude specific objects from activity logging.',
									'logtrail'
								)}
							</span>
						</Flex>

						<Divider />

						<form onSubmit={handleSubmit(onSubmit)}>
							<Flex vertical gap={20}>
								<InputSelectAsync
									name="excludedUsers"
									label={__('Users', 'logtrail')}
									control={control}
									loadOptions={loadUsers}
									isMulti
									extaInfo={__(
										'Exclude specific users from activity logging.',
										'logtrail'
									)}
								/>

								<InputSelect
									name="excludedRoles"
									label={__('Roles', 'logtrail')}
									control={control}
									options={options.roles}
									isMulti
								/>

								<InputSelect
									name="excludedPostTypes"
									label={__('Post Types', 'logtrail')}
									control={control}
									options={options.postTypes}
									isMulti
								/>

								<InputSelect
									name="excludedStatuses"
									label={__('Post Statuses', 'logtrail')}
									control={control}
									options={options.statuses}
									isMulti
								/>

								<Textarea
									name="excludedIPs"
									label={__('IP Addresses', 'logtrail')}
									control={control}
									extaInfo={__(
										'One IP per line.',
										'logtrail'
									)}
								/>

								<Textarea
									name="excludedPostMeta"
									label={__('Post Meta Keys', 'logtrail')}
									control={control}
									extaInfo={__(
										'One meta key per line.',
										'logtrail'
									)}
								/>

								<Textarea
									name="excludedUserMeta"
									label={__('User Meta Keys', 'logtrail')}
									control={control}
									extaInfo={__(
										'One meta key per line.',
										'logtrail'
									)}
								/>

								<InputSelectAsync
									name="excludedPlugins"
									label={__('Plugins', 'logtrail')}
									control={control}
									loadOptions={loadPlugins}
									isMulti
									extaInfo={__(
										'Exclude selected plugins from activity logging.',
										'logtrail'
									)}
								/>

								<InputSelectAsync
									name="excludedThemes"
									label={__('Themes', 'logtrail')}
									control={control}
									loadOptions={loadThemes}
									isMulti
									extaInfo={__(
										'Exclude selected themes from activity logging.',
										'logtrail'
									)}
								/>

								<Switch
									name="excludeCronRequests"
									label={__('Exclude Cron Requests', 'logtrail')}
									control={control}
									extaInfo={__(
										'Skip logs created during wp-cron/background tasks (for example, /wp-cron.php requests).',
										'logtrail'
									)}
								/>

								<Divider />

								<Flex gap={5}>
									<Button
										type="primary"
										htmlType="submit"
										loading={formState.isSubmitting}
										disabled={isResetting}
									>
										{__('Submit', 'logtrail')}
									</Button>

									<Button
										loading={isResetting}
										disabled={
											formState.isSubmitting ||
											isResetting
										}
										onClick={onReset}
									>
										{__('Reset', 'logtrail')}
									</Button>
								</Flex>
							</Flex>
						</form>
					</Flex>
				)}
			</Content>
		</Flex>
	);
};

export default ExcludeSettings;
