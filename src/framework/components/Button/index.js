import React from 'react';
import { Flex, Spinner } from '@framework/components';
import './index.css';

const Button = ({
	type = 'default',
	size = 'medium',
	disabled = false,
	href = null,
	target = null,
	htmlType = 'button',
	icon = null,
	iconPosition = 'start',
	loading = false,
	onClick = null,
	className = '',
	style = {},
	children,
}) => {
	let classNames = ['psm-button', size];
	if (type !== 'default') {
		classNames.push(type);
	}
	if (className !== '') {
		classNames = [...classNames, ...className.split(' ')];
	}

	const gap = size == 'small' ? 5 : 10;
	disabled = loading || disabled;

	return !href ? (
		<button
			type={htmlType}
			className={classNames.join(' ')}
			style={style}
			onClick={onClick}
			disabled={disabled}
		>
			{icon && !loading && (
				<Flex align="center" gap={gap}>
					{iconPosition === 'start' && icon}
					{children}
					{iconPosition === 'end' && icon}
				</Flex>
			)}
			{loading && (
				<Flex align="center" gap={gap}>
					{iconPosition === 'start' && <Spinner />}
					{children}
					{iconPosition === 'end' && <Spinner />}
				</Flex>
			)}
			{!icon && !loading && children}
		</button>
	) : (
		<a href={href} target={target}>
			<button className={classNames.join(' ')} style={style}>
				{icon && (
					<Flex align="center" gap={gap}>
						{iconPosition === 'start' && icon}
						{children}
						{iconPosition === 'end' && icon}
					</Flex>
				)}
				{!icon && children}
			</button>
		</a>
	);
};

export default Button;
