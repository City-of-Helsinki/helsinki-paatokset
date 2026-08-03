const pad = (value: number): string => String(value).padStart(2, '0');

/**
 * Parses a 'YYYY-MM-DD' string as a local date (avoids the UTC shift that
 * `new Date(dateString)` would introduce for visitors outside the UTC+0..3 range).
 */
export const parseLocalDate = (dateString: string): Date => {
  const [year, month, day] = dateString.split('-').map(Number);
  return new Date(year, month - 1, day);
};

/** Short day label without leading zeroes, e.g. '29.7' (matches previous dayjs 'D.M' format). */
export const formatDayShort = (date: Date): string => `${date.getDate()}.${date.getMonth() + 1}`;

/** Zero-padded full date, e.g. '29.07.2026' (matches previous dayjs 'DD.MM.YYYY' format). */
export const formatDayFull = (date: Date): string =>
  `${pad(date.getDate())}.${pad(date.getMonth() + 1)}.${date.getFullYear()}`;

/**
 * Subtracts whole months from a date, clamping to the target month's last day
 * (e.g. 31 July - 3 months = 30 April). Plain `Date#setMonth` would instead roll
 * over into the following month whenever the target month is shorter.
 */
export const subtractMonthsClamped = (date: Date, months: number): Date => {
  const targetMonthIndex = date.getMonth() - months;
  const year = date.getFullYear() + Math.floor(targetMonthIndex / 12);
  const month = ((targetMonthIndex % 12) + 12) % 12;
  const lastDayOfTargetMonth = new Date(year, month + 1, 0).getDate();
  const day = Math.min(date.getDate(), lastDayOfTargetMonth);
  return new Date(year, month, day);
};

/** Steps a calendar month forward/backward, always landing on the 1st (never overflows). */
export const addCalendarMonths = (firstOfMonth: Date, delta: number): Date =>
  new Date(firstOfMonth.getFullYear(), firstOfMonth.getMonth() + delta, 1);

const HELSINKI_TIME_ZONE = 'Europe/Helsinki';

const helsinkiPartsFormatter = new Intl.DateTimeFormat('en-CA', {
  timeZone: HELSINKI_TIME_ZONE,
  year: 'numeric',
  month: '2-digit',
  day: '2-digit',
  hour: '2-digit',
  minute: '2-digit',
  hourCycle: 'h23',
});

export interface HelsinkiParts {
  /** 'YYYY-MM-DD', matching the calendar day grouping the previous PHP endpoint used. */
  dateKey: string;
  /** 'HH:mm' */
  time: string;
}

/**
 * Reads the calendar date and time of a Unix timestamp (seconds) in the site's
 * fixed 'Europe/Helsinki' timezone, regardless of the visitor's own timezone -
 * matching the previous PHP endpoint, which always formatted with the server's
 * configured Drupal timezone (Europe/Helsinki) rather than the visitor's.
 */
export const getHelsinkiParts = (timestampSeconds: number): HelsinkiParts => {
  const parts = helsinkiPartsFormatter.formatToParts(new Date(timestampSeconds * 1000));
  const get = (type: string) => parts.find((part) => part.type === type)?.value ?? '';

  return {
    dateKey: `${get('year')}-${get('month')}-${get('day')}`,
    time: `${get('hour')}:${get('minute')}`,
  };
};
