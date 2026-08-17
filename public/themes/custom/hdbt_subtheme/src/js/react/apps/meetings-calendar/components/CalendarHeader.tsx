import Icon from '@/react/common/Icon';

interface CalendarHeaderProps {
  selectedMonth: Date;
  isPreviousDisabled: boolean;
  onPrevious: () => void;
  onNext: () => void;
}

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
  <div className='meetings-calendar__header'>
    <button
      type='button'
      className={`meetings-calendar__month-button${isPreviousDisabled ? ' meetings-calendar__month-button--disabled' : ''}`}
      onClick={onPrevious}
      disabled={isPreviousDisabled}
      aria-label={Drupal.t('Previous month')}
    >
      <Icon icon='angle-left' />
    </button>
    <h2>
      {Drupal.t(MONTH_LABELS[selectedMonth.getMonth()], {}, { context: 'Long month name' })}{' '}
      {selectedMonth.getFullYear()}
    </h2>
    <button
      type='button'
      className='meetings-calendar__month-button'
      onClick={onNext}
      aria-label={Drupal.t('Next month')}
    >
      <Icon icon='angle-right' />
    </button>
  </div>
);
