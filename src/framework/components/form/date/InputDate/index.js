import React from 'react';
import { Controller, useController } from 'react-hook-form';

// datepicker
import DatePicker, { registerLocale } from 'react-datepicker';
import 'react-datepicker/dist/react-datepicker.css';
import { formatDate, customLocale } from '@framework/utils/formatdate';
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
						selected={
							field.value ? new Date(field.value) : new Date()
						}
						onChange={(date) => {
							field.onChange(formatDate(date));
						}}
						dateFormat="yyyy/MM/dd"
						minDate={props.minDate ? new Date(props.minDate) : null}
						maxDate={props.maxDate ? new Date(props.maxDate) : null}
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
