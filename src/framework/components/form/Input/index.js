import React from 'react';
import { Controller, useController } from 'react-hook-form';
import { Flex } from '@framework/components';
import { Label } from '@framework/components/form';
import '../index.css'; // form styles

const Input = ({
	name,
	control,
	label = null,
	validations = {},
	className = '',
	extaInfo = '',
	htmlAutoComplete = false,
	readonly = false,
	maxlength = null,
	placeholder = null,
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
			<Controller
				name={name}
				control={control}
				rules={validations}
				render={({ field }) => (
					<input
						{...field}
						type="text"
						readOnly={readonly}
						maxLength={maxlength}
						placeholder={placeholder}
						autoComplete={htmlAutoComplete ? 'on' : 'off'}
						onChange={(e) => {
							field.onChange(e);
						}}
					/>
				)}
			/>
			{extaInfo && <small>{extaInfo}</small>}
			{fieldState.error && fieldState.error.message && (
				<small className="psm-form-field-error-msg">
					{fieldState.error.message}
				</small>
			)}
		</Flex>
	);
};

export default Input;
