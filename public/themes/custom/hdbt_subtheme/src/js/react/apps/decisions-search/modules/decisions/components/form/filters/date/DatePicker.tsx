import React from 'react';
import { format, parse } from 'date-fns';
import Calendar from 'react-calendar';
import { IconAngleLeft, IconAngleRight } from 'hds-react';

import i18n from '../../../.././../../i18n';
import { isValidDate } from '../../../../../../utils/Date';

import './DatePicker.scss';

type Props = {
  setTo: Function,
  setFrom: Function,
  from: string,
  to: string
}

const DatePicker = ({ from, to, setFrom, setTo }: Props) => {
  const onSelect = (value: Array<Date>) => {
    if(value[0]) {
      setFrom(format(value[0], 'd.M.y'));
    }
    if(value[1]) {
      setTo(format(value[1], 'd.M.y'));
    }
    else {
      setTo(undefined)
    }
  }
  let value: Array<Date>|null;

  if(isValidDate(from)) {
    value = [parse(from, 'd.M.y', new Date())];

    if(isValidDate(to)) {
      value[1] = parse(to, 'd.M.y', new Date());
    }
  }
  else {
    value = null;
  }

  return (
    <div className='DatePicker'>
      <Calendar
        locale={i18n.language}
        value={value}
        onChange={onSelect}
        selectRange={true}
        allowPartialRange={true}
        nextLabel={<IconAngleRight />}
        next2Label={null}
        prevLabel={<IconAngleLeft />}
        prev2Label={null}
      />
    </div>
  )
}

export default DatePicker;
