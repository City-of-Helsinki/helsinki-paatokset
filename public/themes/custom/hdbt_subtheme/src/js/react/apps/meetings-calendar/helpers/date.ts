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

// Subtracts whole months from a date, clamping to the target month's last day
// (e.g. 31 July - 3 months = 30 April).
export const subtractMonthsClamped = (date: Date, months: number): Date => {
  const targetMonthIndex = date.getMonth() - months;
  const year = date.getFullYear() + Math.floor(targetMonthIndex / 12);
  const month = ((targetMonthIndex % 12) + 12) % 12;
  const lastDayOfTargetMonth = new Date(year, month + 1, 0).getDate();
  const day = Math.min(date.getDate(), lastDayOfTargetMonth);
  return new Date(year, month, day);
};

// Steps a calendar month forward/backward, always landing on the 1st.
export const addCalendarMonths = (firstOfMonth: Date, delta: number): Date =>
  new Date(firstOfMonth.getFullYear(), firstOfMonth.getMonth() + delta, 1);
