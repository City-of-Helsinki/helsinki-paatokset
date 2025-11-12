import {
  Button,
  ButtonPresetTheme,
  Checkbox,
  DateInput,
  IconMinus,
  SelectionGroup,
} from 'hds-react';
import { useAtomValue, useSetAtom } from 'jotai';
import { useState } from 'react';
import { DateTime } from 'luxon';

import { Components } from '../enum/Components';
import {
  getDateSelectionAtom,
  getFromAtom,
  getToAtom,
  setFromAtom,
  setToAtom,
} from '../store';
import Collapsible from '@/react/common/Collapsible';
import { DatePicker } from './DatePicker';
import { HDS_DATE_FORMAT } from '@/react/common/enum/HDSDateFormat';
import { defaultCheckboxStyle } from '@/react/common/constants/checkboxStyle';
import { DateSelection } from '../enum/DateSelection';

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
        <span className='collapsible__title--placeholder'>
          {Drupal.t('Date', {}, { context: 'Decisions search' })}
        </span>
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

  const handleDatePick = (value, callable) => {
    callable(value);
  };

  const handleSelectionClick = (e: MouseEvent<HTMLInputElement>) => {
    if (e.target.checked === false) {
      setFrom(undefined);
      setTo(undefined);
      return;
    }

    const value = (e.target as HTMLInputElement).id;
    const now = DateTime.now();
    setTo(now.toFormat(HDS_DATE_FORMAT));

    switch (value) {
      case DateSelection.PAST_WEEK:
        setFrom(now.minus({ weeks: 1 }).toFormat(HDS_DATE_FORMAT));
        break;
      case DateSelection.PAST_MONTH:
        setFrom(now.minus({ months: 1 }).toFormat(HDS_DATE_FORMAT));
        break;
      case DateSelection.PAST_YEAR:
        setFrom(now.minus({ years: 1 }).toFormat(HDS_DATE_FORMAT));
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
        {calendarActive ? (
          <div className='date-filter__datepicker-wrapper'>
            <div className='date-filter__datepicker-container'>
              <div className='date-filter__fields-container'>
                <DateInput
                  autoFocus
                  label={Drupal.t(
                    'Start date',
                    {},
                    { context: 'Decisions search' },
                  )}
                  language={drupalSettings.path.currentLanguage}
                  name={Components.FROM}
                  onChange={(value) => handleDatePick(value, setFrom)}
                  value={from}
                />
                <IconMinus className='date-filter__fields-divider' />
                <DateInput
                  label={Drupal.t(
                    'End date',
                    {},
                    { context: 'Decisions search' },
                  )}
                  language={drupalSettings.path.currentLanguage}
                  name={Components.TO}
                  onChange={(value) => handleDatePick(value, setTo)}
                  value={to}
                />
              </div>
              <DatePicker
                from={from}
                setFrom={(value) => handleDatePick(value, setFrom)}
                setTo={(value) => handleDatePick(value, setTo)}
                to={to}
              />
            </div>
          </div>
        ) : (
          <div className='date-filter__predefined-ranges-wrapper'>
            <div className='date-filter__predefined-ranges-container'>
              <SelectionGroup>
                <Checkbox
                  id={DateSelection.PAST_WEEK}
                  label={Drupal.t(
                    'Past week',
                    {},
                    { context: 'Decisions search' },
                  )}
                  name='past_week'
                  checked={dateSelection === DateSelection.PAST_WEEK}
                  onClick={handleSelectionClick}
                  style={defaultCheckboxStyle}
                />
                <Checkbox
                  id={DateSelection.PAST_MONTH}
                  label={Drupal.t(
                    'Past month',
                    {},
                    { context: 'Decisions search' },
                  )}
                  name='past_month'
                  checked={dateSelection === DateSelection.PAST_MONTH}
                  onClick={handleSelectionClick}
                  style={defaultCheckboxStyle}
                />
                <Checkbox
                  id={DateSelection.PAST_YEAR}
                  label={Drupal.t(
                    'Past year',
                    {},
                    { context: 'Decisions search' },
                  )}
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
      </Collapsible>
    </div>
  );
};
