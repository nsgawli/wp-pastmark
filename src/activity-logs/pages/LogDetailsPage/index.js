import React, { useEffect, useMemo, useState } from 'react';
import { useNavigate, useParams } from 'react-router-dom';
import { applyFilters } from '@wordpress/hooks';
import { useAlerts } from '@framework/hooks/useAlerts';

import {
	Content,
	Flex,
	Card,
	Button,
	Title,
	ScreenLoader,
	SeverityBadge,
	EventBadge,
	Dropdown,
} from '@framework/components';

import {
	FiArrowLeft,
	FiLink,
	FiCopy,
	FiFileText,
	FiCode,
} from 'react-icons/fi';

import AdminPageHeader from '@framework/components/AdminPageHeader';
import ProductIcon from '@framework/icons/productIcon';

import useLogDetails from '../../hooks/useLogDetails';

import {
	buildLogDetailsUrl,
	getLogJson,
	getLogMarkdown,
} from '../../utils/logDetails';

import '../LogsPage/index.css';
import './index.css';

const LogDetailsPage = () => {
	const navigate = useNavigate();
	const { logId } = useParams();
	const { addAlert } = useAlerts();

	const parsedLogId = useMemo(() => {
		const id = parseInt(logId || '', 10);

		return Number.isInteger(id) && id > 0 ? id : null;
	}, [logId]);

	const { log, loading, loadLog } = useLogDetails();

	const [isCopyActionsOpen, setIsCopyActionsOpen] = useState(false);

	useEffect(() => {
		if (!parsedLogId) {
			navigate('/');
			return;
		}

		loadLog(parsedLogId);
	}, [parsedLogId, loadLog, navigate]);

	const showCopiedAlert = (description) => {
		addAlert({
			id: Date.now(),
			type: 'success',
			title: 'Copied',
			description,
		});
	};

	/**
	 * Extension point: add-ons (e.g. logtrail-pro) can inject additional log
	 * actions (e.g. an "Export" button) via this filter, evaluated at render
	 * time so it doesn't matter that free's script loads before pro's.
	 */
	const logActions = applyFilters('logtrail.activityLogs.logActions', [], {
		log,
	});

	const renderJson = (value) => {
		if (!value) {
			return '-';
		}

		try {
			return JSON.stringify(JSON.parse(value), null, 2);
		} catch (e) {
			return value;
		}
	};

	const writeToClipboard = async (value) => {
		const clipboard =
			typeof window !== 'undefined' ? window.navigator?.clipboard : null;

		if (!clipboard || typeof clipboard.writeText !== 'function') {
			return;
		}

		await clipboard.writeText(value);
	};

	const handleCopyAction = async (key) => {
		if (!log) {
			return;
		}

		switch (key) {
			case 'copy_link':
				await writeToClipboard(buildLogDetailsUrl(log.id));
				showCopiedAlert('Event link copied to clipboard');
				break;

			case 'copy_message':
				await writeToClipboard(log.message || '');
				showCopiedAlert('Event message copied to clipboard');
				break;

			case 'copy_markdown':
				await writeToClipboard(getLogMarkdown(log));
				showCopiedAlert('Markdown copied to clipboard');
				break;

			case 'copy_json':
				await writeToClipboard(getLogJson(log));
				showCopiedAlert('JSON copied to clipboard');
				break;

			default:
				break;
		}

		setIsCopyActionsOpen(false);
	};

	const copyActionsItems = [
		{
			key: 'copy_link',
			label: 'Copy Event Link',
			icon: <FiLink />,
		},
		{
			key: 'copy_message',
			label: 'Copy Message',
			icon: <FiCopy />,
		},
		{
			key: 'copy_markdown',
			label: 'Copy Markdown',
			icon: <FiFileText />,
		},
		{
			key: 'copy_json',
			label: 'Copy JSON',
			icon: <FiCode />,
		},
	];

	return (
		<>
			<AdminPageHeader
				icon={<ProductIcon className="product-icon" />}
				title="LogTrail - User Activity Logger"
			/>

			<Content>
				<Flex vertical gap={20}>
					<Button
						type="default"
						icon={<FiArrowLeft />}
						onClick={() => {
							navigate('/');
						}}
					>
						Back to Logs
					</Button>

					{loading && <ScreenLoader />}

					{!loading && !log && (
						<Card>
							<Flex vertical gap={10}>
								<Title level={4}>Log not found</Title>
								<div>
									This log does not exist or is no longer
									available.
								</div>
							</Flex>
						</Card>
					)}

					{!loading && log && (
						<Flex vertical gap={20}>
							<Card>
								<Flex
									justify="space-between"
									align="center"
									wrap
									gap={12}
								>
									<div>
										<Title level={3}>Log #{log.id}</Title>
										<div className="wptl-log-subtitle">
											<EventBadge
												event={log.event_label}
											/>
										</div>
									</div>

									<Flex
										gap={8}
										wrap
										className="wptl-log-details-actions"
									>
										<div className="wptl-log-details-copy-actions">
											<Dropdown
												label="Copy"
												icon={<FiCopy />}
												size="small"
												items={copyActionsItems}
												isOpenMenu={isCopyActionsOpen}
												setIsOpenMenu={
													setIsCopyActionsOpen
												}
												onMenuItemClick={
													handleCopyAction
												}
												menuPosition="right"
											/>
										</div>

										{logActions.map((action) => (
											<Button
												key={action.key}
												className="wptl-log-details-action-primary"
												size="small"
												type={action.type || 'default'}
												icon={action.icon}
												loading={action.loading}
												onClick={action.onClick}
											>
												{action.label}
											</Button>
										))}
									</Flex>
								</Flex>
							</Card>

							<Card>
								<Flex vertical gap={15}>
									<Title level={5}>Overview</Title>

									<div className="wptl-log-details-grid">
										<div className="wptl-log-details-item">
											<span className="wptl-log-details-label">
												User
											</span>
											<span className="wptl-log-details-value">
												{log.user || '-'}
											</span>
										</div>

										<div className="wptl-log-details-item">
											<span className="wptl-log-details-label">
												Severity
											</span>
											<SeverityBadge
												severity={log.severity_label}
											/>
										</div>

										<div className="wptl-log-details-item">
											<span className="wptl-log-details-label">
												Action
											</span>
											<span className="wptl-log-details-value">
												{log.action_label || '-'}
											</span>
										</div>

										<div className="wptl-log-details-item">
											<span className="wptl-log-details-label">
												Date
											</span>
											<span className="wptl-log-details-value">
												{log.date}
											</span>
										</div>

										<div className="wptl-log-details-item">
											<span className="wptl-log-details-label">
												IP Address
											</span>
											<span className="wptl-log-details-value">
												{log.ip}
											</span>
										</div>
									</div>
								</Flex>
							</Card>

							<Card>
								<Flex vertical gap={10}>
									<Title level={5}>Message</Title>

									<div className="wptl-log-section-content">
										{log.message || '-'}
									</div>
								</Flex>
							</Card>

							{log.before_data && (
								<Card>
									<Flex vertical gap={10}>
										<Title level={5}>Before Data</Title>

										<pre className="wptl-log-json">
											{renderJson(log.before_data)}
										</pre>
									</Flex>
								</Card>
							)}

							{log.after_data && (
								<Card>
									<Flex vertical gap={10}>
										<Title level={5}>After Data</Title>

										<pre className="wptl-log-json">
											{renderJson(log.after_data)}
										</pre>
									</Flex>
								</Card>
							)}

							{log.context && (
								<Card>
									<Flex vertical gap={10}>
										<Title level={5}>Context</Title>

										<pre className="wptl-log-json">
											{renderJson(log.context)}
										</pre>
									</Flex>
								</Card>
							)}
						</Flex>
					)}
				</Flex>
			</Content>
		</>
	);
};

export default LogDetailsPage;
