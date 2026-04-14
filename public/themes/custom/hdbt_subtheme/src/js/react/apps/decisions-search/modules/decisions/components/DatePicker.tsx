import { formatHDSDate, parseHDSDate } from '@/react/common/helpers/dateUtils';
import { IconAngleLeft, IconAngleRight } from 'hds-react';
import Calendar from 'react-calendar';
import type { LooseValue } from 'react-calendar/dist/shared/types.js';

const isValidDate = (_date: unknown) => true;

type Props = {
  // biome-ignore lint/complexity/noBannedTypes: @todo UHF-12501
  setTo: Function;
  // biome-ignore lint/complexity/noBannedTypes: @todo UHF-12501
  setFrom: Function;
  from?: string;
  to?: string;
};

export const DatePicker = ({ from, to, setFrom, setTo }: Props) => {
  const onSelect = (value: LooseValue) => {
    if (!Array.isArray(value)) return;
    if (value[0] instanceof Date) {
      setFrom(formatHDSDate(value[0]));
    }
    if (value[1] instanceof Date) {
      setTo(formatHDSDate(value[1]));
    } else {
      setTo(undefined);
    }
  };

  const getValue = (): LooseValue => {
    let startDate: Date | null = null;
    let endDate: Date | null = null;

    if (from && isValidDate(from)) {
      startDate = parseHDSDate(from);
    }

    if (to && isValidDate(to)) {
      endDate = parseHDSDate(to);
    }

    return [startDate, endDate];
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
