import React from 'react';
import Select from 'react-select';
import { Controller, useController } from 'react-hook-form';
import { Flex } from '@framework/components';
import { Label } from '@framework/components/form';
import '../../index.css'; // form styles
import '../index.css'; // component styles

const InputSelect = ({
	name,
	options,
	control,
	placeholder = '',
	label = null,
	validations = {},
	extaInfo = '',
	isMulti = false,
	isDisabled = false,
	isClearable = true,
	className = '',
	style = {},
	styles = {}, // react-select styles
	formatOptionLabel = (e) => e.label, // react-select formatOptionLabel. We can override this function if needed
}) => {
	const { fieldState } = useController({
		name,
		control,
	});

	// Ensure options are in the correct format for react-select
	const setFieldValue = (field) => {
		const value = field.value;
		if (isMulti) {
			field.value = Array.isArray(value)
				? value.map((item) =>
						item?.label
							? item
							: options.find((option) => option.value === item)
					)
				: [];
		} else {
			field.value = value?.label
				? value
				: options.find((option) => option.value === value);
		}
		return field;
	};

	// Function to get the field value in the format expected by our application
	const getFieldValue = (value) => {
		if (value == null) return isMulti ? [] : '';
		if (isMulti) {
			return value.map((item) => item.value);
		} else {
			return value.value;
		}
	};

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
					<Select
						{...setFieldValue(field)}
						placeholder={placeholder}
						options={options}
						isMulti={isMulti}
						isDisabled={isDisabled}
						isClearable={isClearable}
						menuPortalTarget={document.body}
						classNamePrefix="psm-select"
						styles={styles}
						formatOptionLabel={formatOptionLabel}
						onChange={(value) => {
							field.onChange(getFieldValue(value));
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

export default InputSelect;
