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
import { Switch, Textarea, Time, InputSelect } from '@framework/components/form';
import {
	getEmailReportsSettings,
	updateEmailReportsSettings,
	resetEmailReportsSettings,
	sendEmailReportTest,
} from './resource';
import { useAlerts } from '@framework/hooks/useAlerts';

const toTextareaValue = (recipients) => {
	if (!Array.isArray(recipients)) {
		return '';
	}

	return recipients.join('\n');
};

const toRecipientsArray = (value) => {
	if (!value || typeof value !== 'string') {
		return [];
	}

	return value
		.split('\n')
		.map((email) => email.trim())
		.filter(Boolean);
};

const weeklyDayOptions = [
	{ label: __('Monday', 'logtrail'), value: 'monday' },
	{ label: __('Tuesday', 'logtrail'), value: 'tuesday' },
	{ label: __('Wednesday', 'logtrail'), value: 'wednesday' },
	{ label: __('Thursday', 'logtrail'), value: 'thursday' },
	{ label: __('Friday', 'logtrail'), value: 'friday' },
	{ label: __('Saturday', 'logtrail'), value: 'saturday' },
	{ label: __('Sunday', 'logtrail'), value: 'sunday' },
];

const EmailReports = () => {
	const [isLoading, setIsLoading] = useState(false);
	const [isResetting, setIsResetting] = useState(false);
	const [dailyTestLoading, setDailyTestLoading] = useState(false);
	const [weeklyTestLoading, setWeeklyTestLoading] = useState(false);

	const { addAlert } = useAlerts();

	const { handleSubmit, control, reset, formState, getValues } = useForm({
		defaultValues: {
			enableDailyReport: false,
			dailySendTime: '20:00',
			dailyRecipients: '',
			enableWeeklyReport: false,
			weeklySendDay: 'friday',
			weeklySendTime: '21:00',
			weeklyRecipients: '',
		},
	});

	useEffect(() => {
		const fetchSettings = async () => {
			setIsLoading(true);
			try {
				const response = await getEmailReportsSettings();
				const settings = response?.data ?? response;
				reset({
					enableDailyReport: !!settings?.enableDailyReport,
					dailySendTime: settings?.dailySendTime || '20:00',
					dailyRecipients: toTextareaValue(settings?.dailyRecipients),
					enableWeeklyReport: !!settings?.enableWeeklyReport,
					weeklySendDay: settings?.weeklySendDay || 'friday',
					weeklySendTime: settings?.weeklySendTime || '21:00',
					weeklyRecipients: toTextareaValue(
						settings?.weeklyRecipients
					),
				});
			} catch {
				addAlert({
					id: Date.now(),
					type: 'error',
					title: __('Error', 'logtrail'),
					description: __(
						'Unable to load email report settings.',
						'logtrail'
					),
				});
			} finally {
				setIsLoading(false);
			}
		};

		fetchSettings();
	}, []);

	const onSubmit = async (data) => {
		const payload = {
			enableDailyReport: !!data.enableDailyReport,
			dailySendTime: data.dailySendTime || '20:00',
			dailyRecipients: toRecipientsArray(data.dailyRecipients),
			enableWeeklyReport: !!data.enableWeeklyReport,
			weeklySendDay: data.weeklySendDay || 'friday',
			weeklySendTime: data.weeklySendTime || '21:00',
			weeklyRecipients: toRecipientsArray(data.weeklyRecipients),
		};

		await updateEmailReportsSettings(payload)
			.then(() => {
				addAlert({
					id: Date.now(),
					type: 'success',
					title: __('Success', 'logtrail'),
					description: __(
						'Email report settings saved successfully.',
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
						'Unable to save email report settings.',
						'logtrail'
					),
				});
			});
	};

	const onReset = async () => {
		setIsResetting(true);

		await resetEmailReportsSettings()
			.then(async (response) => {
				const defaults = response?.data ?? response;

				await updateEmailReportsSettings(defaults);

				reset({
					enableDailyReport: !!defaults?.enableDailyReport,
					dailySendTime: defaults?.dailySendTime || '20:00',
					dailyRecipients: toTextareaValue(defaults?.dailyRecipients),
					enableWeeklyReport: !!defaults?.enableWeeklyReport,
					weeklySendDay: defaults?.weeklySendDay || 'friday',
					weeklySendTime: defaults?.weeklySendTime || '21:00',
					weeklyRecipients: toTextareaValue(
						defaults?.weeklyRecipients
					),
				});

				addAlert({
					id: Date.now(),
					type: 'success',
					title: __('Success', 'logtrail'),
					description: __(
						'Email report settings reset successfully.',
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
						'Unable to reset email report settings.',
						'logtrail'
					),
				});
			})
			.finally(() => {
				setIsResetting(false);
			});
	};

	const onSendTestEmail = async (reportType) => {
		const recipientsValue =
			reportType === 'daily'
				? getValues('dailyRecipients')
				: getValues('weeklyRecipients');

		const recipients = toRecipientsArray(recipientsValue);

		if (!recipients.length) {
			addAlert({
				id: Date.now(),
				type: 'error',
				title: __('Error', 'logtrail'),
				description: __(
					'Please enter at least one recipient email address.',
					'logtrail'
				),
			});
			return;
		}

		const setLoading =
			reportType === 'daily' ? setDailyTestLoading : setWeeklyTestLoading;

		setLoading(true);
		await sendEmailReportTest(reportType, recipients)
			.then(() => {
				addAlert({
					id: Date.now(),
					type: 'success',
					title: __('Success', 'logtrail'),
					description: __(
						'Test email sent successfully.',
						'logtrail'
					),
				});
			})
			.catch((error) => {
				const message =
					error?.message ||
					__(
						'Unable to send test email. Please verify recipients and mail configuration.',
						'logtrail'
					);
				addAlert({
					id: Date.now(),
					type: 'error',
					title: __('Error', 'logtrail'),
					description: message,
				});
			})
			.finally(() => {
				setLoading(false);
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
								{__('Email Reports', 'logtrail')}
							</Title>
							<span className="psm-setting-info">
								{__(
									'Configure daily and weekly activity log report emails for your team.',
									'logtrail'
								)}
							</span>
						</Flex>
						<Divider />
						<form onSubmit={handleSubmit(onSubmit)}>
							<Flex vertical gap={20}>
								<Flex vertical gap={12}>
									<Title level={4}>
										{__(
											'Daily Activity Log Report',
											'logtrail'
										)}
									</Title>
									<Switch
										name="enableDailyReport"
										label={__(
											'Enable Daily Report',
											'logtrail'
										)}
										control={control}
									/>
									<Time
										name="dailySendTime"
										label={__('Daily Send Time', 'logtrail')}
										control={control}
										timeIntervals={15}
										extaInfo={__(
											'Choose when the daily report cron should run (site timezone).',
											'logtrail'
										)}
									/>
									<Textarea
										name="dailyRecipients"
										label={__('Recipients', 'logtrail')}
										control={control}
										extaInfo={__(
											'Enter one email address per line.',
											'logtrail'
										)}
									/>
									<Button
										htmlType="button"
										onClick={() => onSendTestEmail('daily')}
										loading={dailyTestLoading}
										disabled={
											formState.isSubmitting ||
											isResetting
										}
									>
										{__('Send Test Email', 'logtrail')}
									</Button>
								</Flex>
								<Divider />
								<Flex vertical gap={12}>
									<Title level={4}>
										{__(
											'Weekly Activity Log Report',
											'logtrail'
										)}
									</Title>
									<Switch
										name="enableWeeklyReport"
										label={__(
											'Enable Weekly Report',
											'logtrail'
										)}
										control={control}
									/>
									<InputSelect
										name="weeklySendDay"
										label={__('Weekly Send Day', 'logtrail')}
										control={control}
										options={weeklyDayOptions}
										isClearable={false}
										extaInfo={__(
											'Choose the weekday for weekly report delivery (site timezone).',
											'logtrail'
										)}
									/>
									<Time
										name="weeklySendTime"
										label={__('Weekly Send Time', 'logtrail')}
										control={control}
										timeIntervals={15}
										extaInfo={__(
											'Choose the time for weekly report delivery (site timezone).',
											'logtrail'
										)}
									/>
									<Textarea
										name="weeklyRecipients"
										label={__('Recipients', 'logtrail')}
										control={control}
										extaInfo={__(
											'Enter one email address per line.',
											'logtrail'
										)}
									/>
									<Button
										htmlType="button"
										onClick={() =>
											onSendTestEmail('weekly')
										}
										loading={weeklyTestLoading}
										disabled={
											formState.isSubmitting ||
											isResetting
										}
									>
										{__('Send Test Email', 'logtrail')}
									</Button>
								</Flex>
								<Divider />
								<Flex gap={5}>
									<Button
										type="primary"
										htmlType="submit"
										loading={formState.isSubmitting}
										disabled={
											isResetting ||
											dailyTestLoading ||
											weeklyTestLoading
										}
									>
										{__('Submit', 'logtrail')}
									</Button>
									<Button
										loading={isResetting}
										disabled={
											formState.isSubmitting ||
											isResetting ||
											dailyTestLoading ||
											weeklyTestLoading
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

export default EmailReports;
