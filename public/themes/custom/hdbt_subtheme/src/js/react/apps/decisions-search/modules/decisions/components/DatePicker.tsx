import { formatHDSDate, parseHDSDate } from '@/react/common/helpers/dateUtils';
import { IconAngleLeft, IconAngleRight } from 'hds-react';
import Calendar from 'react-calendar';

const isValidDate = (_date) => true;

type Props = {
  // biome-ignore lint/complexity/noBannedTypes: @todo UHF-12501
  setTo: Function;
  // biome-ignore lint/complexity/noBannedTypes: @todo UHF-12501
  setFrom: Function;
  from: string;
  to: string;
};

export const DatePicker = ({ from, to, setFrom, setTo }: Props) => {
  const onSelect = (value: Array<Date>) => {
    if (value[0]) {
      setFrom(formatHDSDate(value[0]));
    }
    if (value[1]) {
      setTo(formatHDSDate(value[1]));
    } else {
      setTo(undefined);
    }
  };

  const getValue = () => {
    const value = [null, null];

    if (from && isValidDate(from)) {
      value[0] = parseHDSDate(from);
    }

    if (to && isValidDate(to)) {
      value[1] = parseHDSDate(to);
    }

    return value;
  };

  return (
    <div className='decision-search__date-picker hdbt-search__dropdown'>
      <Calendar
        locale={drupalSettings.path.currentLanguage}
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
