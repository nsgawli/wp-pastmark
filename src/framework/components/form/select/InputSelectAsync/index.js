import React from 'react';
import AsyncSelect from 'react-select/async';
import { Controller, useController } from 'react-hook-form';
import { Flex } from '@framework/components';
import { Label } from '@framework/components/form';
import '../../index.css'; // form styles
import '../index.css'; // component styles

const InputSelectAsync = ({
	name,
	loadOptions,
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
	cacheOptions = true,
	defaultOptions = true,
}) => {
	const { fieldState } = useController({
		name,
		control,
	});

	// Ensure options are in the correct format for react-select
	const setFieldValue = (field, options) => {
		const value = field.value;
		if (!options) return field;
		if (isMulti) {
			field.value = value.map(
				(item) =>
					options.find(
						(option) =>
							option.value ===
							(item && item.value !== undefined
								? item.value
								: item)
					) || item
			);
		}
		return field;
	};

	// Function to get the field value in the format expected by our application
	const getFieldValue = (value) => {
		if (isMulti) {
			return value || [];
		}

		return value || null;
	};

	className = className ? `psm-form-field ${className}` : 'psm-form-field';

	return (
		<Flex className={className} style={style} vertical gap={3}>
			{label && <Label text={label} required={!!validations.required} />}
			<Controller
				name={name}
				control={control}
				rules={validations}
				render={({ field }) => (
					<AsyncSelect
						{...setFieldValue(
							field,
							field.options || (field._f && field._f.options)
						)}
						placeholder={placeholder}
						loadOptions={loadOptions}
						isMulti={isMulti}
						isDisabled={isDisabled}
						isClearable={isClearable}
						menuPortalTarget={document.body}
						classNamePrefix="psm-select"
						styles={styles}
						formatOptionLabel={formatOptionLabel}
						cacheOptions={cacheOptions}
						defaultOptions={defaultOptions}
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

export default InputSelectAsync;
