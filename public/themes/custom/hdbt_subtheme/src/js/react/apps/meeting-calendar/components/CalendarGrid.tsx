import type { CalendarDay as CalendarDayData } from '../types/Meeting';
import { CalendarDay } from './CalendarDay';

interface CalendarGridProps {
  days: CalendarDayData[];
  todayDate: string;
}

export const CalendarGrid = ({ days, todayDate }: CalendarGridProps) => (
  <div className='calendar-month'>
    <ol className='days-grid'>
      {days.map((day) => (
        <CalendarDay key={day.date} day={day} isToday={day.date === todayDate} />
      ))}
    </ol>
  </div>
);
