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
import { Switch, Input } from '@framework/components/form';
import { getSecuritySettings, updateSecuritySettings } from './resource';
import { useAlerts } from '@framework/hooks/useAlerts';

const DEFAULT_VALUES = {
	anonymizeIp: false,
	loginThrottleWindow: 300,
	loginThrottleMax: 5,
};

const SecuritySettings = () => {
	const [isLoading, setIsLoading] = useState(false);
	const { addAlert } = useAlerts();
	const { handleSubmit, control, reset, formState } = useForm({
		defaultValues: DEFAULT_VALUES,
	});

	useEffect(() => {
		const fetchSettings = async () => {
			setIsLoading(true);

			try {
				const response = await getSecuritySettings();
				const settings = response?.data ?? response;

				reset({
					anonymizeIp: !!settings?.anonymizeIp,
					loginThrottleWindow:
						settings?.loginThrottleWindow ??
						DEFAULT_VALUES.loginThrottleWindow,
					loginThrottleMax:
						settings?.loginThrottleMax ??
						DEFAULT_VALUES.loginThrottleMax,
				});
			} catch {
				addAlert({
					id: Date.now(),
					type: 'error',
					title: __('Error', 'pastmark'),
					description: __(
						'Unable to load security & privacy settings.',
						'pastmark'
					),
				});
			} finally {
				setIsLoading(false);
			}
		};

		fetchSettings();
	}, []);

	const onSubmit = async (data) => {
		await updateSecuritySettings({
			anonymizeIp: !!data.anonymizeIp,
			loginThrottleWindow: Number(data.loginThrottleWindow),
			loginThrottleMax: Number(data.loginThrottleMax),
		})
			.then((response) => {
				const settings = response?.data ?? response;

				// The backend clamps out-of-range values (see
				// RestApi\Settings\Security::update_settings()) — reflect
				// whatever it actually saved, not just what was submitted.
				reset({
					anonymizeIp: !!settings?.anonymizeIp,
					loginThrottleWindow:
						settings?.loginThrottleWindow ??
						DEFAULT_VALUES.loginThrottleWindow,
					loginThrottleMax:
						settings?.loginThrottleMax ??
						DEFAULT_VALUES.loginThrottleMax,
				});

				addAlert({
					id: Date.now(),
					type: 'success',
					title: __('Success', 'pastmark'),
					description: __(
						'Security & privacy settings saved successfully.',
						'pastmark'
					),
				});
			})
			.catch(() => {
				addAlert({
					id: Date.now(),
					type: 'error',
					title: __('Error', 'pastmark'),
					description: __(
						'Unable to save security & privacy settings.',
						'pastmark'
					),
				});
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
								{__('Security & Privacy', 'pastmark')}
							</Title>
							<span className="psm-setting-info">
								{__(
									'Control how sensitive request data is handled: IP address privacy and failed-login throttling.',
									'pastmark'
								)}
							</span>
						</Flex>
						<Divider />
						<form onSubmit={handleSubmit(onSubmit)}>
							<Flex vertical gap={20}>
								<Switch
									name="anonymizeIp"
									label={__(
										'Anonymize IP Addresses',
										'pastmark'
									)}
									control={control}
									extaInfo={__(
										'When enabled, newly logged IP addresses are stored anonymized (the last octet zeroed for IPv4, the network prefix only for IPv6). This only affects new log rows going forward — it cannot be undone for rows already stored, and IP-based exclusion rules keep working correctly against the real address either way.',
										'pastmark'
									)}
								/>
								<Divider />
								<Flex vertical gap={5}>
									<Title level={4}>
										{__(
											'Failed-Login Throttling',
											'pastmark'
										)}
									</Title>
									<span className="psm-setting-info">
										{__(
											'A burst of repeated failed logins for the same username and IP is logged individually up to the threshold below, then combined into one continuously-updating entry instead of creating a new row per attempt.',
											'pastmark'
										)}
									</span>
								</Flex>
								<Flex gap={20} wrap>
									<Input
										name="loginThrottleWindow"
										label={__(
											'Throttle Window (seconds)',
											'pastmark'
										)}
										control={control}
										extaInfo={__(
											'How long a burst is tracked before resetting. Default: 300 (5 minutes). Allowed range: 60–3600.',
											'pastmark'
										)}
										style={{ maxWidth: '260px' }}
									/>
									<Input
										name="loginThrottleMax"
										label={__(
											'Throttle Threshold (attempts)',
											'pastmark'
										)}
										control={control}
										extaInfo={__(
											'Individual attempts logged before throttling kicks in. Default: 5. Allowed range: 1–100.',
											'pastmark'
										)}
										style={{ maxWidth: '260px' }}
									/>
								</Flex>
								<Divider />
								<Flex gap={5}>
									<Button
										type="primary"
										htmlType="submit"
										loading={formState.isSubmitting}
									>
										{__('Submit', 'pastmark')}
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

export default SecuritySettings;
