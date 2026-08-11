import React, { useState, useEffect } from 'react';
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
	Switch,
	RadioButton,
	Input,
	InputSelect,
	Label,
} from '@framework/components/form';

import {
	eventTimestampOptions,
	logDetailsViewModeOptions,
	logsPageViewModeOptions,
	autoDeleteLogsUnitOptions,
	getGeneralSettings,
	updateGeneralSettings,
	resetGeneralSettings,
} from './resource';
import { useAlerts } from '@framework/hooks/useAlerts';

const GeneralSettings = () => {
	const [isLoading, setIsLoading] = useState(false);
	const [isResetting, setIsResetting] = useState(false);
	const { handleSubmit, watch, control, formState, reset } = useForm();
	const { addAlert } = useAlerts();

	useEffect(() => {
		const fetchSettings = async () => {
			setIsLoading(true);
			try {
				const response = await getGeneralSettings();
				const generalSettings = response?.data ?? response;
				reset({
					logDetailsViewMode: 'drawer',
					logsPageViewMode: 'timeline',
					...generalSettings,
				});
			} catch (error) {
				console.error('Error fetching settings:', error);
			} finally {
				setIsLoading(false);
			}
		};
		fetchSettings();
	}, []);

	const onSubmit = async (data) => {
		await updateGeneralSettings(data)
			.then(() => {
				addAlert({
					id: Date.now(),
					type: 'success',
					title: __('Success', 'logtrail'),
					description: __(
						'General settings saved successfully.',
						'logtrail'
					),
				});
			})
			.catch((error) => {
				console.error('Error updating settings:', error);
				addAlert({
					id: Date.now(),
					type: 'error',
					title: __('Error', 'logtrail'),
					description: __(
						'Unable to save general settings.',
						'logtrail'
					),
				});
			});
	};

	const onReset = async () => {
		setIsResetting(true);
		await resetGeneralSettings()
		.then(async (response) => {
			const defaultSettings = response?.data ?? response;
			await updateGeneralSettings(defaultSettings);
			reset(defaultSettings);
			addAlert({
				id: Date.now(),
				type: 'success',
				title: __('Success', 'logtrail'),
				description: __(
					'General settings reset successfully.',
					'logtrail'
				),
			});
		})
		.catch((error) => {
			console.error('Error resetting settings:', error);
			addAlert({
				id: Date.now(),
				type: 'error',
				title: __('Error', 'logtrail'),
				description: __(
					'Unable to reset general settings.',
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
			style={{ flexGrow: 1, minWidth: 0, maxWidth: '800px' }}
		>
			<Content>
				{isLoading && <ScreenLoader />}
				{!isLoading && (
					<Flex vertical gap={10}>
						<Flex vertical gap={5}>
							<Title level={3}>
								{__('General Settings', 'logtrail')}
							</Title>
							<span className="psm-setting-info">
								{__(
									'This section allows you to configure plugin-related settings to customize its behavior and improve your experience.',
									'logtrail'
								)}
							</span>
						</Flex>
						<Divider />
						<form onSubmit={handleSubmit(onSubmit)}>
							<Flex vertical gap={20}>
								<Switch
									name="dashboardWidget"
									label={__('Dashboard Widget', 'logtrail')}
									control={control}
									extaInfo={__(
										'Show a widget on the WordPress dashboard with the latest logs.',
										'logtrail'
									)}
								/>
								<RadioButton
									name="eventTimestamp"
									label={__('Event Timestamp', 'logtrail')}
									control={control}
									options={eventTimestampOptions}
									extaInfo={__(
										'Choose how event timestamps are displayed in logs.',
										'logtrail'
									)}
								/>
								<RadioButton
									name="logsPageViewMode"
									label={__('Logs Page View', 'logtrail')}
									control={control}
									options={logsPageViewModeOptions}
									extaInfo={__(
										'Choose whether the logs page displays activity logs as a table or a timeline.',
										'logtrail'
									)}
								/>
								<RadioButton
									name="logDetailsViewMode"
									label={__('Log Details View', 'logtrail')}
									control={control}
									options={logDetailsViewModeOptions}
									extaInfo={__(
										'Choose whether log details open in a drawer or on a dedicated page.',
										'logtrail'
									)}
								/>
								<Switch
									name="enableAutoDeleteLogs"
									label={__('Auto Delete Logs', 'logtrail')}
									control={control}
									extaInfo={__(
										'Automatically deleting logs after a certain period.',
										'logtrail'
									)}
								/>
								{watch('enableAutoDeleteLogs') && (
									<Flex vertical gap={5}>
										<Label
											text={__(
												'Auto Delete Logs',
												'logtrail'
											)}
										/>
										<Flex gap={5} wrap>
											<Input
												name="autoDeleteTime"
												control={control}
												style={{
													maxWidth: '200px',
												}}
											/>
											<InputSelect
												control={control}
												name="autoDeleteUnit"
												options={
													autoDeleteLogsUnitOptions
												}
												isClearable={false}
												style={{
													minWidth: '100px',
												}}
											/>
										</Flex>
										<div>
											<small>
												{__(
													'Logs older than the specified time will be automatically deleted.',
													'logtrail'
												)}
											</small>
										</div>
									</Flex>
								)}
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

export default GeneralSettings;
