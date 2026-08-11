import React from 'react';
import { __ } from '@wordpress/i18n';
import { Controller, useController } from 'react-hook-form';

// datepicker
import DatePicker, { registerLocale } from 'react-datepicker';
import 'react-datepicker/dist/react-datepicker.css';
import { formatTime, customLocale } from '@framework/utils/formatdate';
registerLocale('custom-en', customLocale);

import { Flex } from '@framework/components';
import { Label } from '@framework/components/form';
import '../../index.css'; // form styles
import '../index.css'; // date styles

const Time = (props) => {
	const {
		name,
		control,
		label = null,
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
						showTimeSelect
						showTimeSelectOnly
						timeIntervals={props.timeIntervals || 30}
						selected={
							field.value
								? new Date(`2024-01-01 ${field.value}:00`)
								: new Date()
						}
						onChange={(date) => {
							field.onChange(formatTime(date));
						}}
						timeCaption={__('Time', 'psmwraq')}
						dateFormat="h:mm aa"
						minTime={
							props.minTime
								? new Date(`2024-01-01T${props.minTime}:00`)
								: null
						}
						maxTime={
							props.maxTime
								? new Date(`2024-01-01T${props.maxTime}:00`)
								: null
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

export default Time;
