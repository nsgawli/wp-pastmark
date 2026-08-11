import React from 'react';
import { Controller, useController } from 'react-hook-form';
import { Flex } from '@framework/components';
import { Label } from '@framework/components/form';
import '../index.css'; // form styles
import './index.css'; // radio button styles

const RadioButton = ({
	name,
	options,
	control,
	label = null,
	validations = {},
	className = '',
	extaInfo = '',
	style = {},
}) => {
	const { fieldState } = useController({
		name,
		control,
	});

	className = className ? `psm-form-field ${className}` : 'psm-form-field';

	return (
		<Flex className={className} style={style} vertical gap={3}>
			{label && (
				<Label
					text={label}
					required={validations.required ? true : false}
				/>
			)}
			{extaInfo && <small>{extaInfo}</small>}
			<Controller
				name={name}
				control={control}
				rules={validations}
				defaultValue={[]}
				render={({ field }) => (
					<>
						{options.map((option, index) => (
							<label key={index} className="psm-radio-option">
								<input
									type="radio"
									{...field}
									value={option.value}
									checked={field.value === option.value}
									onChange={() => {
										field.onChange(option.value);
									}}
								/>
								{option.label}
							</label>
						))}
					</>
				)}
			/>
			{fieldState.error && fieldState.error.message && (
				<small className="psm-form-field-error-msg">
					{fieldState.error.message}
				</small>
			)}
		</Flex>
	);
};

export default RadioButton;
