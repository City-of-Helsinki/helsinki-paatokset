import { useMemo, useState } from 'react';
import useSWR from 'swr';
import { formatHTMLDate } from '@/react/common/helpers/dateUtils';
import { CalendarGrid } from '../components/CalendarGrid';
import { CalendarHeader } from '../components/CalendarHeader';
import { getCalendarDates } from '../helpers/calendarDays';
import { addCalendarMonths, subtractMonthsClamped } from '../helpers/date';
import type { CalendarDay, MeetingsByDate } from '../types/Meeting';

const getPathPrefix = (): string => {
  const settings = drupalSettings as unknown as { path?: { pathPrefix?: string } };
  return settings.path?.pathPrefix ?? '';
};

const getMeetingsUrl = (fromDate: string): string =>
  `${window.location.origin}/${getPathPrefix()}ahjo_api/meetings?from=${fromDate}`;

const fetchMeetings = async (url: string): Promise<MeetingsByDate> => {
  const response = await fetch(url);
  const json = await response.json();
  return json.data ?? {};
};

const today = new Date();
const todayDate = formatHTMLDate(today);
const initialMonth = new Date(today.getFullYear(), today.getMonth(), 1);
const originStartMonth = addCalendarMonths(initialMonth, -12);

// Two-stage load: a fast recent window first, then a full year in the background
// so early navigation isn't blocked on the heavier query.
const recentMeetingsUrl = getMeetingsUrl(formatHTMLDate(subtractMonthsClamped(today, 3)));
const fullMeetingsUrl = getMeetingsUrl(formatHTMLDate(subtractMonthsClamped(today, 12)));

export const MeetingCalendarContainer = () => {
  const [selectedMonth, setSelectedMonth] = useState(initialMonth);

  const { data: recentMeetings } = useSWR(recentMeetingsUrl, fetchMeetings);
  const { data: fullMeetings } = useSWR(fullMeetingsUrl, fetchMeetings);

  const meetings = fullMeetings ?? recentMeetings;
  const isReady = Boolean(meetings);

  const isPreviousDisabled = addCalendarMonths(selectedMonth, -1) < originStartMonth;

  const days = useMemo<CalendarDay[]>(() => {
    if (!meetings) {
      return [];
    }
    return getCalendarDates(selectedMonth.getFullYear(), selectedMonth.getMonth() + 1).map((day) => ({
      ...day,
      meetings: meetings[day.date] ?? [],
    }));
  }, [meetings, selectedMonth]);

  const handlePrevious = () => {
    setSelectedMonth((current) => {
      const previous = addCalendarMonths(current, -1);
      return previous < originStartMonth ? current : previous;
    });
  };

  const handleNext = () => {
    setSelectedMonth((current) => addCalendarMonths(current, 1));
  };

  return (
    <div className='meetings-calendar container'>
      {!isReady && (
        <div className='hds-loading-spinner'>
          <div />
          <div />
          <div />
        </div>
      )}
      {isReady && (
        <>
          <CalendarHeader
            selectedMonth={selectedMonth}
            isPreviousDisabled={isPreviousDisabled}
            onPrevious={handlePrevious}
            onNext={handleNext}
          />
          <CalendarGrid days={days} todayDate={todayDate} />
        </>
      )}
    </div>
  );
};
