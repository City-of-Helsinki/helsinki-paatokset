import { Button, ButtonPresetTheme, Checkbox, DateInput, IconMinus, SelectionGroup } from 'hds-react';
import { useAtomValue, useSetAtom } from 'jotai';
import { type MouseEvent, useState } from 'react';
import { addDays, addMonths, addYears, formatHDSDate } from '@/react/common/helpers/dateUtils';

import { Components } from '../enum/Components';
import { getDateSelectionAtom, getFromAtom, getToAtom, setFromAtom, setToAtom } from '../store';
import Collapsible from '@/react/common/Collapsible';
import { defaultCheckboxStyle } from '@/react/common/constants/checkboxStyle';
import { DateSelection } from '../enum/DateSelection';
import { DatePicker } from './DatePicker';

export const DateFilter = () => {
  const from = useAtomValue(getFromAtom);
  const setFrom = useSetAtom(setFromAtom);
  const to = useAtomValue(getToAtom);
  const setTo = useSetAtom(setToAtom);
  const dateSelection = useAtomValue(getDateSelectionAtom);
  const [calendarActive, setCalendarActive] = useState<boolean>(false);

  const getTitle = () => {
    if (!from && !to) {
      return (
        <span className='collapsible__title--placeholder'>{Drupal.t('Date', {}, { context: 'Decisions search' })}</span>
      );
    }

    let titleString = '';
    if (from) {
      titleString += from;
    }
    if (to) {
      titleString += (from ? ' - ' : '- ') + to;
    }

    return titleString;
  };

  const handleDatePick = (value: string, callable: (value: string) => void) => {
    callable(value);
  };

  const handleSelectionClick = (e: MouseEvent) => {
    if ((e.target as HTMLInputElement).checked === false) {
      setFrom(undefined);
      setTo(undefined);
      return;
    }

    const value = (e.target as HTMLInputElement).id;
    const now = new Date();
    setTo(formatHDSDate(now));

    switch (value) {
      case DateSelection.PAST_WEEK:
        setFrom(formatHDSDate(addDays(now, -7)));
        break;
      case DateSelection.PAST_MONTH:
        setFrom(formatHDSDate(addMonths(now, -1)));
        break;
      case DateSelection.PAST_YEAR:
        setFrom(formatHDSDate(addYears(now, -1)));
        break;
    }
  };

  return (
    <div className='hdbt-search--react__dropdown decisions-search__date-filter'>
      <Collapsible
        className='decisions-search__date-filter-collapsible'
        id='date-filter'
        title={getTitle()}
        label={Drupal.t('Date', {}, { context: 'Decisions search' })}
      >
        <div>
          {calendarActive ? (
            <div className='date-filter__datepicker-wrapper'>
              <div className='date-filter__datepicker-container'>
                <div className='date-filter__fields-container'>
                  <DateInput
                    autoFocus
                    id={Components.FROM}
                    label={Drupal.t('Start date', {}, { context: 'Decisions search' })}
                    language={drupalSettings.path.currentLanguage}
                    name={Components.FROM}
                    onChange={(value) => handleDatePick(value, setFrom)}
                    value={from?.toString()}
                  />
                  <IconMinus className='date-filter__fields-divider' />
                  <DateInput
                    id={Components.TO}
                    label={Drupal.t('End date', {}, { context: 'Decisions search' })}
                    language={drupalSettings.path.currentLanguage}
                    name={Components.TO}
                    onChange={(value) => handleDatePick(value, setTo)}
                    value={to?.toString()}
                  />
                  <DatePicker from={from} to={to?.toString()} setFrom={setFrom} setTo={setTo} />
                </div>
              </div>
            </div>
          ) : (
            <div className='date-filter__predefined-ranges-wrapper'>
              <div className='date-filter__predefined-ranges-container'>
                <SelectionGroup>
                  <Checkbox
                    id={DateSelection.PAST_WEEK}
                    label={Drupal.t('Past week', {}, { context: 'Decisions search' })}
                    name='past_week'
                    checked={dateSelection === DateSelection.PAST_WEEK}
                    onClick={handleSelectionClick}
                    style={defaultCheckboxStyle}
                  />
                  <Checkbox
                    id={DateSelection.PAST_MONTH}
                    label={Drupal.t('Past month', {}, { context: 'Decisions search' })}
                    name='past_month'
                    checked={dateSelection === DateSelection.PAST_MONTH}
                    onClick={handleSelectionClick}
                    style={defaultCheckboxStyle}
                  />
                  <Checkbox
                    id={DateSelection.PAST_YEAR}
                    label={Drupal.t('Past year', {}, { context: 'Decisions search' })}
                    name='past_year'
                    checked={dateSelection === DateSelection.PAST_YEAR}
                    onClick={handleSelectionClick}
                    style={defaultCheckboxStyle}
                  />
                </SelectionGroup>
              </div>
            </div>
          )}
          <Button
            className='date-filter__inner-control'
            onClick={() => setCalendarActive(!calendarActive)}
            type='button'
            theme={ButtonPresetTheme.Black}
          >
            {calendarActive
              ? Drupal.t('Back', {}, { context: 'Decisions search' })
              : Drupal.t('Choose range', {}, { context: 'Decisions search' })}
          </Button>
        </div>
      </Collapsible>
    </div>
  );
};
