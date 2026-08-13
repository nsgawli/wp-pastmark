import React from 'react';
import { Controller, useController } from 'react-hook-form';

// datepicker
import DatePicker, { registerLocale } from 'react-datepicker';
import 'react-datepicker/dist/react-datepicker.css';
import { formatDate, customLocale } from '@framework/utils/formatdate';
import { parseDateOnly } from '@framework/utils/dateOnly';
registerLocale('custom-en', customLocale);

import { Flex } from '@framework/components';
import { Label } from '@framework/components/form';
import '../../index.css'; // form styles
import '../index.css'; // date styles

const InputDate = (props) => {
	const {
		name,
		control,
		label,
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
			<Controller
				name={name}
				control={control}
				rules={validations}
				render={({ field }) => (
					<DatePicker
						showTimeSelect={false}
						// `null` (not `new Date()`) when unset — otherwise
						// the picker highlights today as if it were already
						// chosen, and a field left untouched (e.g. the "To"
						// side of a range, on a day that happens to match
						// today) silently submits an empty value while
						// looking selected.
						selected={
							field.value ? parseDateOnly(field.value) : null
						}
						openToDate={
							field.value
								? parseDateOnly(field.value)
								: new Date()
						}
						placeholderText={props.placeholderText || 'Select date'}
						isClearable
						onChange={(date) => {
							field.onChange(date ? formatDate(date) : '');
						}}
						dateFormat="yyyy/MM/dd"
						minDate={
							props.minDate ? parseDateOnly(props.minDate) : null
						}
						maxDate={
							props.maxDate ? parseDateOnly(props.maxDate) : null
						}
						locale="custom-en"
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

export default InputDate;
