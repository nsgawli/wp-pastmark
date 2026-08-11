import React from 'react';
import { Controller, useController } from 'react-hook-form';
import { Flex } from '@framework/components';
import { Label } from '@framework/components/form';
import '../index.css'; // form styles
import './index.css'; // switch styles

const Switch = (props) => {
	const {
		name,
		control,
		label = null,
		disabled = false,
		validations = {},
		className = '',
		extaInfo = '',
		style = {},
	} = props;
	const { fieldState } = useController({
		name,
		control,
	});

	return (
		<Flex
			className={
				className ? `psm-form-field ${className}` : 'psm-form-field'
			}
			style={style}
			vertical
			gap={3}
		>
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
				defaultValue={false}
				render={({ field }) => (
					<label className="psm-switch">
						<input
							type="checkbox"
							{...field}
							disabled={disabled}
							checked={field.value}
							onChange={(e) => {
								field.onChange(e.target.checked);
							}}
						/>
						<span
							className={`psm-switch-slider${disabled ? ' disabled' : ''}`}
						></span>
					</label>
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

export default Switch;
