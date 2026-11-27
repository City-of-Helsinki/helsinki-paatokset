import * as Sentry from '@sentry/react';
import { LoadingSpinner } from 'hds-react';
import { useEffect, useMemo, useState } from 'react';
import useSWR, { preload } from 'swr';
import { formatHTMLDate } from '@/react/common/helpers/dateUtils';
import ResultsError from '@/react/common/ResultsError';
import { CalendarGrid } from '../components/CalendarGrid';
import { CalendarHeader } from '../components/CalendarHeader';
import { getCalendarDates } from '../helpers/calendarDays';
import { addCalendarMonths, formatMonth, getChunkStart, MONTHS_PER_CHUNK } from '../helpers/date';
import type { CalendarDay, MeetingsByDate } from '../types/Meeting';

// Each request returns meetings for MONTHS_PER_CHUNK months starting from chunkStart.
const getMeetingsUrl = (chunkStart: Date): string =>
  `${window.location.origin}/${drupalSettings.path.currentLanguage}/ahjo_api/meetings?month=${formatMonth(chunkStart)}`;

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

export const MeetingCalendarContainer = () => {
  const [selectedMonth, setSelectedMonth] = useState(initialMonth);

  const chunkStart = getChunkStart(selectedMonth);
  const { data: meetings, error } = useSWR(getMeetingsUrl(chunkStart), fetchMeetings);
  const isReady = Boolean(meetings);

  // Start loading the adjacent chunk when the user reaches the edge of the
  // current one, so that moving to the next month does not show a spinner.
  useEffect(() => {
    const chunk = getChunkStart(selectedMonth);
    const monthInChunk = selectedMonth.getMonth() - chunk.getMonth();

    if (monthInChunk === MONTHS_PER_CHUNK - 1) {
      preload(getMeetingsUrl(addCalendarMonths(chunk, MONTHS_PER_CHUNK)), fetchMeetings);
    }

    // Last month of the previous chunk must be reachable.
    if (monthInChunk === 0 && addCalendarMonths(chunk, -1) >= originStartMonth) {
      preload(getMeetingsUrl(addCalendarMonths(chunk, -MONTHS_PER_CHUNK)), fetchMeetings);
    }
  }, [selectedMonth]);

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
