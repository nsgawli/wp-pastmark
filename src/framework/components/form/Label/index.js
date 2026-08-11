import React from 'react';
import { Flex, Tooltip } from '@framework/components';
import { BsQuestionCircle } from 'react-icons/bs';
import './../index.css'; // form styles
import './index.css'; // component styles

const Label = ({ text, required, info = '' }) => {
	return (
		<Flex className="psm-form-field-label" gap={3}>
			{text}
			{info && (
				<Tooltip content={info}>
					<BsQuestionCircle />
				</Tooltip>
			)}
			{required && <span className="psm-form-field-required">*</span>}
		</Flex>
	);
};

export default Label;
