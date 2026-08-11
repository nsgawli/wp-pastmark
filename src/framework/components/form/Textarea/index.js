import React from 'react';
import { Controller, useController } from 'react-hook-form';
import { Flex } from '@framework/components';
import { Label } from '@framework/components/form';
import '../index.css'; // form styles

const Textarea = ({
	name,
	control,
	label = null,
	validations = {},
	className = '',
	extaInfo = '',
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
					<textarea
						{...field}
						readOnly={readonly}
						maxLength={maxlength}
						placeholder={placeholder}
						rows={4}
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

export default Textarea;
