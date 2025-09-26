import React from 'react';
import { format, parse } from 'date-fns';
import Calendar from 'react-calendar';
import { IconAngleLeft, IconAngleRight } from 'hds-react';

import i18n from '../../../../../../i18n';
import { isValidDate } from '../../../../../../utils/Date';

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
      setTo(undefined);
    }
  };
  let value: Array<Date>|null;

  const getValue = () => {
    const value = [null, null];

    if (from && isValidDate(from)) {
      value[0] = parse(from, 'd.M.y', new Date());
    }

    if (to && isValidDate(to)) {
      value[1] = parse(to, 'd.M.y', new Date());
    }

    return value;
  };

  return (
    <div className='decision-search-date-picker'>
      <Calendar
        locale={i18n.language}
        value={getValue()}
        onChange={onSelect}
        selectRange
        allowPartialRange
        nextLabel={<IconAngleRight />}
        next2Label={null}
        prevLabel={<IconAngleLeft />}
        prev2Label={null}
      />
    </div>
  );
};

export default DatePicker;
