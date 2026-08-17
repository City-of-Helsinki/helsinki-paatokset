import * as Sentry from '@sentry/react';
import { LoadingSpinner } from 'hds-react';
import { useMemo, useState } from 'react';
import useSWR from 'swr';
import { formatHTMLDate } from '@/react/common/helpers/dateUtils';
import ResultsError from '@/react/common/ResultsError';
import { CalendarGrid } from '../components/CalendarGrid';
import { CalendarHeader } from '../components/CalendarHeader';
import { getCalendarDates } from '../helpers/calendarDays';
import { addCalendarMonths, subtractMonthsClamped } from '../helpers/date';
import type { CalendarDay, MeetingsByDate } from '../types/Meeting';

const getMeetingsUrl = (fromDate: string): string =>
  `${window.location.origin}/${drupalSettings.path.currentLanguage}/ahjo_api/meetings?from=${fromDate}`;

const fetchMeetings = async (url: string): Promise<MeetingsByDate> => {
  const response = await fetch(url);

  if (!response.ok) {
    const error = new Error(`Meeting calendar request failed with status ${response.status}`);
    Sentry.captureException(error);
    throw error;
  }

  const json = await response.json();
  return json.data ?? {};
};

const today = new Date();
const todayDate = formatHTMLDate(today);
const initialMonth = new Date(today.getFullYear(), today.getMonth(), 1);
const originStartMonth = addCalendarMonths(initialMonth, -12);

const recentMeetingsUrl = getMeetingsUrl(formatHTMLDate(subtractMonthsClamped(today, 3)));
const fullMeetingsUrl = getMeetingsUrl(formatHTMLDate(subtractMonthsClamped(today, 12)));

export const MeetingCalendarContainer = () => {
  const [selectedMonth, setSelectedMonth] = useState(initialMonth);

  const { data: recentMeetings, error: recentError } = useSWR(recentMeetingsUrl, fetchMeetings);
  const { data: fullMeetings, error: fullError } = useSWR(fullMeetingsUrl, fetchMeetings);

  const meetings = fullMeetings ?? recentMeetings;
  const isReady = Boolean(meetings);

  const error = !meetings ? recentError || fullError : undefined;

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
    <div className='container'>
      {error && <ResultsError error={error} />}
      {!isReady && !error && <LoadingSpinner loadingText='' loadingFinishedText='' className='hds-loading-spinner' />}
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
