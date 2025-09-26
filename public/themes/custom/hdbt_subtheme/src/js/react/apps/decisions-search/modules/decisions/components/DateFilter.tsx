import { Button, ButtonPresetTheme, Checkbox, DateInput, IconMinus, SelectionGroup } from 'hds-react';
import { useAtomValue, useSetAtom } from 'jotai';
import { useState } from 'react';
import { DateTime } from 'luxon';

import { Components } from '../enum/Components';
import { getDateSelectionAtom, getFromAtom, getToAtom, setDateSelectionAtom, setFromAtom, setToAtom } from '../store';
import Collapsible from '@/react/common/Collapsible';
import { DatePicker } from './DatePicker';
import { HDS_DATE_FORMAT } from '@/react/common/enum/HDSDateFormat';
import { defaultCheckboxStyle } from '@/react/common/constants/checkboxStyle';

const PAST_WEEK = Symbol('PAST_WEEK');
const PAST_MONTH = Symbol('PAST_MONTH');
const PAST_YEAR = Symbol('PAST_YEAR');

export const DateFilter = () => {
  const from = useAtomValue(getFromAtom);
  const setFrom = useSetAtom(setFromAtom);
  const to = useAtomValue(getToAtom);
  const setTo = useSetAtom(setToAtom);
  const dateSelection = useAtomValue(getDateSelectionAtom);
  const setDateSelection = useSetAtom(setDateSelectionAtom);
  const [calendarActive, setCalendarActive] = useState<boolean>(false);

  const getTitle = () => {
    if (!from && !to) {
      return <span className='collapsible__title--placeholder'>
        {Drupal.t('Date', {}, {context: 'Decisions search'})}
      </span>;
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
    setDateSelection(undefined);
    callable(value);
  };

  const handleSelectionClick = (value: string) => {
    const now = DateTime.now();
    setTo(now.toFormat(HDS_DATE_FORMAT));
    
    switch (value) {
      case PAST_WEEK:
        setFrom(now.minus({weeks: 1}).toFormat(HDS_DATE_FORMAT));
        break;
      case PAST_MONTH:
        setFrom(now.minus({months: 1}).toFormat(HDS_DATE_FORMAT));
        break;
      case PAST_YEAR:
        setFrom(now.minus({years: 1}).toFormat(HDS_DATE_FORMAT));
        break;
    }

    setDateSelection(value);
  };

  return (
    <div className='hdbt-search--react__dropdown decisions-search__date-filter'>
      <Collapsible
        className='decisions-search__date-filter-collapsible'
        id='date-filter'
        title={getTitle()}
        label={Drupal.t('Date', {}, {context: 'Decisions search'})}
      >
        {calendarActive ?
          <div className='date-filter__datepicker-wrapper'>
            <div className='date-filter__datepicker-container'>
              <div className='date-filter__fields-container'>
                <DateInput
                  autoFocus
                  label={Drupal.t('Start date', {}, {context: 'Decisions search'})}
                  name={Components.FROM}
                  onChange={(value) => handleDatePick(value, setFrom)}
                  value={from}
                />
                <IconMinus className='date-filter__fields-divider' />
                <DateInput
                  label={Drupal.t('End date', {}, {context: 'Decisions search'})}
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
          :     
          <div className="date-filter__predefined-ranges-wrapper">
            <div className='date-filter__predefined-ranges-container'>
              <SelectionGroup>
                <Checkbox
                  id='past_week'
                  label={Drupal.t('Past week', {}, {context: 'Decisions search'})}
                  name='past_week' 
                  checked={dateSelection === PAST_WEEK}
                  onClick={() => handleSelectionClick(PAST_WEEK)}
                  style={defaultCheckboxStyle}
                />
                <Checkbox
                  id='past_month'
                  label={Drupal.t('Past month', {}, {context: 'Decisions search'})}
                  name='past_month'
                  checked={dateSelection === PAST_MONTH}
                  onClick={() => handleSelectionClick(PAST_MONTH)}
                  style={defaultCheckboxStyle}
                />
                <Checkbox
                  id='past_year'
                  label={Drupal.t('Past year', {}, {context: 'Decisions search'})}
                  name='past_year'
                  checked={dateSelection === PAST_YEAR}
                  onClick={() => handleSelectionClick(PAST_YEAR)}
                  style={defaultCheckboxStyle}
                />
              </SelectionGroup>
            </div>
          </div>}
          <Button
            className='date-filter__inner-control'
            onClick={() => setCalendarActive(!calendarActive)}
            type='button'
            theme={ButtonPresetTheme.Black}
          >
            {calendarActive ?
              Drupal.t('Back', {}, {context: 'Decisions search'}) :
              Drupal.t('Choose range', {}, {context: 'Decisions search'})
            }
          </Button>
      </Collapsible>
    </div>
  );
};
