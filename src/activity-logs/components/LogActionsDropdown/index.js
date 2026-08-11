import React, { useEffect, useRef, useState } from 'react';
import { applyFilters } from '@wordpress/hooks';
import { useAlerts } from '@framework/hooks/useAlerts';

import { FiEye, FiLink, FiCopy, FiFileText, FiCode } from 'react-icons/fi';

import {
	buildLogDetailsUrl,
	getLogJson,
	getLogMarkdown,
} from '../../utils/logDetails';

import './index.css';

const LogActionsDropdown = ({ log, onView }) => {
	const [isOpen, setIsOpen] = useState(false);
	const containerRef = useRef(null);
	const openTimerRef = useRef(null);
	const closeTimerRef = useRef(null);

	const { addAlert } = useAlerts();

	useEffect(() => {
		const handleOutsideClick = (event) => {
			if (
				containerRef.current &&
				!containerRef.current.contains(event.target)
			) {
				if (openTimerRef.current) {
					window.clearTimeout(openTimerRef.current);
					openTimerRef.current = null;
				}

				if (closeTimerRef.current) {
					window.clearTimeout(closeTimerRef.current);
					closeTimerRef.current = null;
				}

				setIsOpen(false);
			}
		};

		document.addEventListener('mousedown', handleOutsideClick);

		return () => {
			if (openTimerRef.current) {
				window.clearTimeout(openTimerRef.current);
			}

			if (closeTimerRef.current) {
				window.clearTimeout(closeTimerRef.current);
			}

			document.removeEventListener('mousedown', handleOutsideClick);
		};
	}, []);

	const openMenuWithDelay = () => {
		if (closeTimerRef.current) {
			window.clearTimeout(closeTimerRef.current);
			closeTimerRef.current = null;
		}

		if (isOpen || openTimerRef.current) {
			return;
		}

		openTimerRef.current = window.setTimeout(() => {
			setIsOpen(true);
			openTimerRef.current = null;
		}, 120);
	};

	const closeMenuWithDelay = () => {
		if (openTimerRef.current) {
			window.clearTimeout(openTimerRef.current);
			openTimerRef.current = null;
		}

		if (!isOpen || closeTimerRef.current) {
			return;
		}

		closeTimerRef.current = window.setTimeout(() => {
			setIsOpen(false);
			closeTimerRef.current = null;
		}, 160);
	};

	const showCopiedAlert = (message) => {
		addAlert({
			id: Date.now(),
			type: 'success',
			title: 'Copied',
			description: message,
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

	const items = [
		{
			key: 'view',
			label: 'View Details',
			icon: <FiEye />,
		},
		{
			key: 'link',
			label: 'Copy Event Link',
			icon: <FiLink />,
		},
		{
			key: 'message',
			label: 'Copy Message',
			icon: <FiCopy />,
		},
		{
			key: 'markdown',
			label: 'Copy Markdown',
			icon: <FiFileText />,
		},
		{
			key: 'json',
			label: 'Copy JSON',
			icon: <FiCode />,
		},
	];

	/**
	 * Extension point: add-ons (e.g. logtrail-pro) can inject additional
	 * per-row actions (e.g. "Create Similar Log") by filtering this list,
	 * evaluated at render time. Items with their own `onClick` are
	 * dispatched generically in `handleAction` below.
	 */
	const filteredItems = applyFilters(
		'logtrail.activityLogs.rowActions',
		items,
		{ log }
	);

	const ActionsIcon = () => (
		<svg
			width="16"
			height="16"
			viewBox="0 0 16 16"
			fill="none"
			aria-hidden="true"
		>
			<circle cx="8" cy="3" r="1.5" fill="currentColor" />
			<circle cx="8" cy="8" r="1.5" fill="currentColor" />
			<circle cx="8" cy="13" r="1.5" fill="currentColor" />
		</svg>
	);

	const handleAction = async (key) => {
		const filteredItem = filteredItems.find((item) => item.key === key);

		if (filteredItem?.onClick) {
			await filteredItem.onClick(log);
			setIsOpen(false);
			return;
		}

		switch (key) {
			case 'view':
				onView(log);
				break;

			case 'link': {
				const link = buildLogDetailsUrl(log.id);

				await writeToClipboard(link);
				showCopiedAlert('Event link copied to clipboard');
				break;
			}

			case 'message':
				await writeToClipboard(log.message || '');
				showCopiedAlert('Event message copied to clipboard');
				break;

			case 'json':
				await writeToClipboard(getLogJson(log));
				showCopiedAlert('JSON copied to clipboard');
				break;

			case 'markdown':
				await writeToClipboard(getLogMarkdown(log));
				showCopiedAlert('Markdown copied to clipboard');
				break;

			default:
				break;
		}

		setIsOpen(false);
	};

	return (
		<div
			ref={containerRef}
			className="wptl-log-actions-dropdown"
			onMouseEnter={openMenuWithDelay}
			onMouseLeave={closeMenuWithDelay}
		>
			<button
				type="button"
				className="wptl-log-actions-trigger"
				aria-label="Show actions"
				onClick={() => {
					if (openTimerRef.current) {
						window.clearTimeout(openTimerRef.current);
						openTimerRef.current = null;
					}

					if (closeTimerRef.current) {
						window.clearTimeout(closeTimerRef.current);
						closeTimerRef.current = null;
					}

					setIsOpen((prev) => !prev);
				}}
			>
				<ActionsIcon />
			</button>

			<div
				className={`wptl-log-actions-menu${isOpen ? ' is-open' : ''}`}
				role="menu"
				aria-hidden={!isOpen}
			>
				{filteredItems.map((item) => (
					<button
						key={item.key}
						type="button"
						className="wptl-log-actions-item"
						onClick={() => {
							handleAction(item.key);
						}}
					>
						<span className="wptl-log-actions-item-icon">
							{item.icon}
						</span>
						<span>{item.label}</span>
					</button>
				))}
			</div>
		</div>
	);
};

export default LogActionsDropdown;
