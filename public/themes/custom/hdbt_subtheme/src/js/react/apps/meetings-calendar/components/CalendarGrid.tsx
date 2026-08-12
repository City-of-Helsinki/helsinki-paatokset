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

const CalendarDay = ({ day, isToday }: CalendarDayProps) => {
  const date = parseLocalDate(day.date);
  const hasMeetings = day.meetings.length > 0;

  const classes = [
    'meetings-calendar__day',
    isToday && 'calendar-day__today',
    !hasMeetings && 'meetings-calendar__day--no-meetings',
  ]
    .filter(Boolean)
    .join(' ');

  return (
    <li className={classes}>
      <h3 className='meetings-calendar__day__header'>
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
        <div className='meetings-calendar__meeting__title'>{Drupal.t('No meetings')}</div>
      )}
    </li>
  );
};

interface CalendarGridProps {
  days: CalendarDayData[];
  todayDate: string;
}

export const CalendarGrid = ({ days, todayDate }: CalendarGridProps) => (
  <ul className='meetings-calendar__grid'>
    {days.map((day) => (
      <CalendarDay key={day.date} day={day} isToday={day.date === todayDate} />
    ))}
  </ul>
);
