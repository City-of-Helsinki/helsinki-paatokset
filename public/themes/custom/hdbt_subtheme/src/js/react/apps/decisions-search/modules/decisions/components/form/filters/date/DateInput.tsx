import React from 'react';
import { IconCalendarPlus, DateInput as Input } from 'hds-react';
import classNames from 'classnames';
import './DateInput.scss';

type Props = {
  autoFocus?: boolean,
  label: string,
  defaultValue?: string,
  error?: string,
  name: string,
  setDate: Function,
  onChange: Function
}

const DateInput = ({ autoFocus, defaultValue, label, setDate, error, name, onChange }: Props) => {
  const handleChange = (event: React.FocusEvent<HTMLInputElement>) => {
    onChange(event.target.value);
  }

  const invalid = !!error;
  
  return (
    <div
      className={classNames(
        'DateInput',
        'DateInput-wrapper',
        {'has-input': defaultValue && defaultValue.length > 0}
      )}
    >
      <IconCalendarPlus />
      <Input
        name={name}
        id={name}
        label={label}
        onBlur={handleChange}
        disableDatePicker
        helperText='pp.kk.vvvv'
        invalid={invalid}
        value={defaultValue}
        autoFocus={autoFocus}
      />
    </div>
  );
}

export default DateInput;
