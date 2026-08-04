import { formatDayShort, parseLocalDate } from '../helpers/date';
import type { CalendarDay as CalendarDayData } from '../types/Meeting';
import { MeetingRow } from './MeetingRow';

interface CalendarDayProps {
  day: CalendarDayData;
  isToday: boolean;
}

const WEEKDAY_LABELS: Record<number, string> = {
  0: 'Sunday',
  1: 'Monday',
  2: 'Tuesday',
  3: 'Wednesday',
  4: 'Thursday',
  5: 'Friday',
  6: 'Saturday',
};

export const CalendarDay = ({ day, isToday }: CalendarDayProps) => {
  const date = parseLocalDate(day.date);
  const hasMeetings = day.meetings.length > 0;

  const classes = ['calendar-day', isToday && 'calendar-day__today', !hasMeetings && 'calendar-day__no-meetings']
    .filter(Boolean)
    .join(' ');

  return (
    <li className={classes}>
      <h3 className='date-header'>
        <span>{Drupal.t(WEEKDAY_LABELS[date.getDay()], {}, { context: 'Meeting calendar weekday.' })}</span>
        <span>{formatDayShort(date)}.</span>
      </h3>
      {hasMeetings ? (
        day.meetings.map((meeting) => (
          <MeetingRow
            key={`${meeting.policymaker}-${meeting.start_time}-${meeting.title}`}
            meeting={meeting}
            date={date}
          />
        ))
      ) : (
        <div className='no-meetings meeting-title'>{Drupal.t('No meetings')}</div>
      )}
    </li>
  );
};
