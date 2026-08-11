import React, { useEffect, useState } from 'react';
import { __, sprintf } from '@wordpress/i18n';
import { useForm } from 'react-hook-form';
import {
	Flex,
	Content,
	Title,
	Button,
	ScreenLoader,
	Divider,
} from '@framework/components';
import { Switch } from '@framework/components/form';
import {
	getDataManagementSettings,
	updateDataManagementSettings,
	deleteOldDataInstantly,
} from './resource';
import { useAlerts } from '@framework/hooks/useAlerts';

const DataManagement = () => {
	const [isLoading, setIsLoading] = useState(false);
	const [isDeleting, setIsDeleting] = useState(false);
	const { addAlert } = useAlerts();
	const { handleSubmit, control, reset, formState } = useForm({
		defaultValues: {
			removeDataOnUninstall: false,
		},
	});

	useEffect(() => {
		const fetchSettings = async () => {
			setIsLoading(true);

			try {
				const response = await getDataManagementSettings();
				const settings = response?.data ?? response;

				reset({
					removeDataOnUninstall: !!settings?.removeDataOnUninstall,
				});
			} catch {
				addAlert({
					id: Date.now(),
					type: 'error',
					title: __('Error', 'logtrail'),
					description: __(
						'Unable to load data management settings.',
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
		await updateDataManagementSettings({
			removeDataOnUninstall: !!data.removeDataOnUninstall,
		})
			.then(() => {
				addAlert({
					id: Date.now(),
					type: 'success',
					title: __('Success', 'logtrail'),
					description: __(
						'Data management settings saved successfully.',
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
						'Unable to save data management settings.',
						'logtrail'
					),
				});
			});
	};

	const onDeleteOldData = async () => {
		const confirmed = window.confirm(
			__(
				'Are you sure you want to delete all activity logs instantly? This action cannot be undone.',
				'logtrail'
			)
		);

		if (!confirmed) {
			return;
		}

		setIsDeleting(true);

		await deleteOldDataInstantly()
			.then((response) => {
				const data = response?.data ?? response;
				const deleted = Number(data?.deleted || 0);

				addAlert({
					id: Date.now(),
					type: 'success',
					title: __('Success', 'logtrail'),
					description: sprintf(
						/* translators: %d: number of deleted log entries. */
						__(
							'Deleted %d activity log entries successfully.',
							'logtrail'
						),
						deleted
					),
				});
			})
			.catch(() => {
				addAlert({
					id: Date.now(),
					type: 'error',
					title: __('Error', 'logtrail'),
					description: __(
						'Unable to delete old data instantly.',
						'logtrail'
					),
				});
			})
			.finally(() => {
				setIsDeleting(false);
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
								{__('Data Management', 'logtrail')}
							</Title>
							<span className="psm-setting-info">
								{__(
									'Manage data cleanup behavior and maintenance actions for activity logs.',
									'logtrail'
								)}
							</span>
						</Flex>
						<Divider />
						<form onSubmit={handleSubmit(onSubmit)}>
							<Flex vertical gap={20}>
								<Switch
									name="removeDataOnUninstall"
									label={__('Remove Data On Uninstall', 'logtrail')}
									control={control}
									extaInfo={__(
										'When enabled, plugin logs and settings are permanently deleted when you uninstall the plugin.',
										'logtrail'
									)}
								/>
								<Flex vertical gap={8}>
									<Title level={4}>
										{__('Delete Old Data Instantly', 'logtrail')}
									</Title>
									<span className="psm-setting-info">
										{__(
											'Use this action to immediately delete all stored activity logs.',
											'logtrail'
										)}
									</span>
									<Button
										htmlType="button"
										onClick={onDeleteOldData}
										loading={isDeleting}
										disabled={formState.isSubmitting}
										style={{
											color: 'var(--psmfr-danger-color)',
											borderColor: 'var(--psmfr-danger-color)',
										}}
									>
										{__('Delete Data Instantly', 'logtrail')}
									</Button>
								</Flex>
								<Divider />
								<Flex gap={5}>
									<Button
										type="primary"
										htmlType="submit"
										loading={formState.isSubmitting}
										disabled={isDeleting}
									>
										{__('Submit', 'logtrail')}
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

export default DataManagement;
