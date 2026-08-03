interface CalendarHeaderProps {
  selectedMonth: Date;
  isPreviousDisabled: boolean;
  onPrevious: () => void;
  onNext: () => void;
}

// Source strings and context kept identical to the previous Vue implementation
// (js/meetings_calendar.js) so existing Drupal translations keep matching.
const MONTH_LABELS = [
  'January',
  'February',
  'March',
  'April',
  'May',
  'June',
  'July',
  'August',
  'September',
  'October',
  'November',
  'December',
];

export const CalendarHeader = ({ selectedMonth, isPreviousDisabled, onPrevious, onNext }: CalendarHeaderProps) => (
  <div className='calendar-header'>
    <button
      type='button'
      className={`icon-container${isPreviousDisabled ? ' icon-container--disabled' : ''}`}
      onClick={onPrevious}
      disabled={isPreviousDisabled}
      aria-label={Drupal.t('Previous month')}
    >
      <i className='hel-icon hel-icon--angle-left' />
    </button>
    <h2>
      {Drupal.t(MONTH_LABELS[selectedMonth.getMonth()], {}, { context: 'Long month name' })}{' '}
      {selectedMonth.getFullYear()}
    </h2>
    <button type='button' className='icon-container' onClick={onNext} aria-label={Drupal.t('Next month')}>
      <i className='hel-icon hel-icon--angle-right' />
    </button>
  </div>
);
