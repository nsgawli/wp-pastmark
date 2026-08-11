import React, { useState } from 'react';
import { applyFilters } from '@wordpress/hooks';
import { useAlerts } from '@framework/hooks/useAlerts';

import {
	Drawer,
	Flex,
	Title,
	Button,
	Card,
	SeverityBadge,
	EventBadge,
	Dropdown,
} from '@framework/components';

import { FiCopy, FiLink, FiFileText, FiCode } from 'react-icons/fi';

import {
	buildLogDetailsUrl,
	getLogJson,
	getLogMarkdown,
} from '../../utils/logDetails';

import './index.css';

const LogDetailsDrawer = ({ log = null, isOpen = false, onClose = null }) => {
	const [isCopyActionsOpen, setIsCopyActionsOpen] = useState(false);
	const { addAlert } = useAlerts();

	if (!log) {
		return null;
	}

	/**
	 * Extension point: add-ons (e.g. logtrail-pro) can inject additional log
	 * actions (e.g. an "Export" button) via this filter, evaluated at render
	 * time so it doesn't matter that free's script loads before pro's.
	 */
	const logActions = applyFilters('logtrail.activityLogs.logActions', [], {
		log,
	});

	const showCopiedAlert = (description) => {
		addAlert({
			id: Date.now(),
			type: 'success',
			title: 'Copied',
			description,
		});
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

	return (
		<Drawer isOpen={isOpen} onClose={onClose}>
			<Flex className="wptl-drawer-log-details" vertical gap={20}>
				<Flex
					className="wptl-drawer-log-header"
					justify="space-between"
					align="center"
					wrap
					gap={12}
				>
					<div>
						<Title level={3}>Log #{log.id}</Title>

						<div className="wptl-drawer-log-subtitle">
							<EventBadge event={log.event_label} />
						</div>
					</div>

					<Flex className="wptl-drawer-log-header-actions" gap={8}>
						{logActions.map((action) => (
							<Button
								key={action.key}
								size="small"
								type={action.type || 'default'}
								icon={action.icon}
								loading={action.loading}
								onClick={action.onClick}
							>
								{action.label}
							</Button>
						))}

						<Dropdown
							label="Copy"
							icon={<FiCopy />}
							size="small"
							items={copyActionsItems}
							isOpenMenu={isCopyActionsOpen}
							setIsOpenMenu={setIsCopyActionsOpen}
							onMenuItemClick={handleCopyAction}
							menuPosition="right"
						/>
					</Flex>
				</Flex>

				<Card>
					<Flex vertical gap={15}>
						<Title level={5}>Overview</Title>

						<div className="wptl-drawer-log-details-grid">
							<div className="wptl-drawer-log-details-item">
								<span className="wptl-drawer-log-details-label">
									User
								</span>
								<span className="wptl-drawer-log-details-value">
									{log.user || '-'}
								</span>
							</div>

							<div className="wptl-drawer-log-details-item">
								<span className="wptl-drawer-log-details-label">
									Severity
								</span>
								<SeverityBadge severity={log.severity_label} />
							</div>

							<div className="wptl-drawer-log-details-item">
								<span className="wptl-drawer-log-details-label">
									Action
								</span>
								<span className="wptl-drawer-log-details-value">
									{log.action_label || '-'}
								</span>
							</div>

							<div className="wptl-drawer-log-details-item">
								<span className="wptl-drawer-log-details-label">
									Date
								</span>
								<span className="wptl-drawer-log-details-value">
									{log.date}
								</span>
							</div>

							<div className="wptl-drawer-log-details-item">
								<span className="wptl-drawer-log-details-label">
									IP Address
								</span>
								<span className="wptl-drawer-log-details-value">
									{log.ip}
								</span>
							</div>
						</div>
					</Flex>
				</Card>

				<Card>
					<Flex vertical gap={10}>
						<Title level={5}>Message</Title>

						<div className="wptl-drawer-log-section-content">
							{log.message || '-'}
						</div>
					</Flex>
				</Card>

				{log.before_data && (
					<Card>
						<Flex vertical gap={10}>
							<Title level={5}>Before Data</Title>

							<pre className="wptl-drawer-log-json">
								{renderJson(log.before_data)}
							</pre>
						</Flex>
					</Card>
				)}

				{log.after_data && (
					<Card>
						<Flex vertical gap={10}>
							<Title level={5}>After Data</Title>

							<pre className="wptl-drawer-log-json">
								{renderJson(log.after_data)}
							</pre>
						</Flex>
					</Card>
				)}

				{log.context && (
					<Card>
						<Flex vertical gap={10}>
							<Title level={5}>Context</Title>

							<pre className="wptl-drawer-log-json">
								{renderJson(log.context)}
							</pre>
						</Flex>
					</Card>
				)}
			</Flex>
		</Drawer>
	);
};

export default LogDetailsDrawer;
