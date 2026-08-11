import React, { useState } from 'react';
import { __ } from '@wordpress/i18n';
import { useScreenSize } from '@framework/hooks/useScreenSize';
import { Flex, MenuItem, Tooltip } from '@framework/components';
import { BsChevronDoubleLeft, BsChevronDoubleRight } from 'react-icons/bs';
import './index.css';

const SideMenu = ({
	items = [],
	current = null,
	onClick = null,
	className = '',
	collapseKey = 'psmSideMenuCollapse',
	style = {},
}) => {
	const { screenSize } = useScreenSize();
	const isMobile = ['xs', 'sm'].includes(screenSize);

	const [isMenuCollapsed, setIsMenuCollapsed] = useState(() => {
		return JSON.parse(localStorage.getItem(collapseKey)) || false;
	});

	let classNames = ['psm-side-menu'];
	if (className !== '') {
		classNames = [...classNames, ...className.split(' ')];
	}

	const toggleMenuCollapse = () => {
		setIsMenuCollapsed((prev) => {
			const newState = !prev;
			localStorage.setItem(collapseKey, JSON.stringify(newState));
			return newState;
		});
	};

	return (
		<Flex vertical className={classNames.join(' ')} gap={5} style={style}>
			{items.map((item) =>
				!isMobile && isMenuCollapsed ? (
					<Tooltip content={item.label} key={item.key}>
						<MenuItem
							{...item}
							current={item.key == current}
							onClick={() => onClick(item.key)}
							gap={10}
							parentType="side"
							collapsed={isMenuCollapsed}
						/>
					</Tooltip>
				) : (
					<MenuItem
						{...item}
						current={item.key == current}
						onClick={() => onClick(item.key)}
						gap={10}
						parentType="side"
						collapsed={isMenuCollapsed}
					/>
				)
			)}
			{!isMobile &&
				(isMenuCollapsed ? (
					<Tooltip content={__('Expand', 'psmwraq')}>
						<MenuItem
							icon={<BsChevronDoubleRight />}
							label={__('Expand', 'psmwraq')}
							onClick={toggleMenuCollapse}
							gap={10}
							parentType="side"
							collapsed={isMenuCollapsed}
						/>
					</Tooltip>
				) : (
					<MenuItem
						icon={<BsChevronDoubleLeft />}
						label={__('Collapse', 'psmwraq')}
						onClick={toggleMenuCollapse}
						gap={10}
						parentType="side"
						collapsed={isMenuCollapsed}
					/>
				))}
		</Flex>
	);
};

export default SideMenu;
