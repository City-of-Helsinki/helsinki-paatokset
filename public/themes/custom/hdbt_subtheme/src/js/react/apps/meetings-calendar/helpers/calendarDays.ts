import { addDays, formatHTMLDate } from '@/react/common/helpers/dateUtils';

const SUNDAY = 0;
const MONDAY = 1;
const SATURDAY = 6;

interface RawDay {
  date: Date;
  isCurrentMonth: boolean;
}

export interface CalendarDayMeta {
  date: string;
  isCurrentMonth: boolean;
}

const daysInMonth = (year: number, month: number): number => new Date(year, month, 0).getDate();

const isWeekend = (date: Date): boolean => date.getDay() === SATURDAY || date.getDay() === SUNDAY;

const buildCurrentMonthDays = (year: number, month: number): RawDay[] => {
  const total = daysInMonth(year, month);
  return Array.from({ length: total }, (_, index) => ({
    date: new Date(year, month - 1, index + 1),
    isCurrentMonth: true,
  }));
};

// Leads in with the days from the previous month needed to fill the week the 1st falls in.
// If the 1st falls on a weekend, no leading days are added - the weekend filter below removes
// the 1st itself and the grid naturally starts on the following Monday.
const buildPreviousMonthDays = (firstOfMonth: Date): RawDay[] => {
  const firstDayWeekday = firstOfMonth.getDay();

  if (firstDayWeekday === SATURDAY || firstDayWeekday === SUNDAY) {
    return [];
  }

  const visibleDays = firstDayWeekday === MONDAY ? 0 : firstDayWeekday - 1;
  const start = addDays(firstOfMonth, -visibleDays);

  return Array.from({ length: visibleDays }, (_, index) => ({
    date: addDays(start, index),
    isCurrentMonth: false,
  }));
};

// Trails with the days from the next month needed to fill the week the last day falls in.
const buildNextMonthDays = (year: number, month: number): RawDay[] => {
  const lastOfMonth = new Date(year, month - 1, daysInMonth(year, month));
  const lastDayWeekday = lastOfMonth.getDay();
  const visibleDays = lastDayWeekday === SUNDAY ? 0 : 7 - lastDayWeekday;
  const nextMonthStart = new Date(year, month, 1);

  return Array.from({ length: visibleDays }, (_, index) => ({
    date: addDays(nextMonthStart, index),
    isCurrentMonth: false,
  }));
};

/** Builds the Mon-Fri calendar grid for a given month, including the leading/trailing days needed to fill full weeks. */
export const getCalendarDates = (year: number, month: number): CalendarDayMeta[] => {
  const firstOfMonth = new Date(year, month - 1, 1);
  const days = [
    ...buildPreviousMonthDays(firstOfMonth),
    ...buildCurrentMonthDays(year, month),
    ...buildNextMonthDays(year, month),
  ];

  return days
    .filter((day) => !isWeekend(day.date))
    .map((day) => ({ date: formatHTMLDate(day.date), isCurrentMonth: day.isCurrentMonth }));
};
