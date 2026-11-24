// Zero-pads a single-digit number to 2 digits, e.g. 7 -> '07'.
const pad = (value: number): string => String(value).padStart(2, '0');

//Parses a 'YYYY-MM-DD' string as a local date
export const parseLocalDate = (dateString: string): Date => {
  const [year, month, day] = dateString.split('-').map(Number);
  return new Date(year, month - 1, day);
};

// Short day label without leading zeroes, e.g. 29.7
export const formatDayShort = (date: Date): string => `${date.getDate()}.${date.getMonth() + 1}`;

export const formatDayFull = (date: Date): string =>
  `${pad(date.getDate())}.${pad(date.getMonth() + 1)}.${date.getFullYear()}`;

// Steps a calendar month forward/backward, always landing on the 1st.
export const addCalendarMonths = (firstOfMonth: Date, delta: number): Date =>
  new Date(firstOfMonth.getFullYear(), firstOfMonth.getMonth() + delta, 1);

// Formats a date as 'YYYY-MM'.
export const formatMonth = (date: Date): string => `${date.getFullYear()}-${pad(date.getMonth() + 1)}`;

// Number of months loaded by a single meetings API request.
export const MONTHS_PER_CHUNK = 3;

// First month of the chunk the given month belongs to. Chunks are aligned to
// calendar quarters so that all clients request the same (cacheable) urls.
export const getChunkStart = (date: Date): Date =>
  new Date(date.getFullYear(), Math.floor(date.getMonth() / MONTHS_PER_CHUNK) * MONTHS_PER_CHUNK, 1);
