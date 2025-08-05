import React, { useState, useEffect, useCallback, useRef } from 'react';
import { Button, Checkbox, IconAngleUp, IconAngleDown, IconMinus, IconCalendar, IconAngleLeft, SelectionGroup } from 'hds-react';
import { format, parse, subWeeks, subMonths, subYears } from 'date-fns';
import { useTranslation } from 'react-i18next';

import classNames from 'classnames';
import useOutsideClick from '../../../../../../hooks/useOutsideClick';
import DateInput from './DateInput';
import { FormErrors } from '../../../../types/types';
import { isValidDate } from '../../../../../../utils/Date';
import DatePicker from './DatePicker';


type Props = {
  setQuery: Function,
  errors: FormErrors,
  setErrors: Function,
  ariaControls?: string,
  from: any,
  to: any,
  setFrom: Function,
  setTo: Function,
  queryFrom: any,
  queryTo: any
  selection: any,
  setSelection: Function,
};

type Query = {
  query: {
    range: {
      meeting_date: {
        gte?: string,
        lte?: string
      }
    }
  }
}

const selections = {
  PAST_WEEK: 'PAST_WEEK',
  PAST_MONTH: 'PAST_MONTH',
  PAST_YEAR: 'PAST_YEAR'
};

const DateSelect = ({
    ariaControls,
    errors, setErrors,
    setQuery,
    from,
    to,
    setFrom,
    setTo,
    queryFrom,
    queryTo,
    selection,
    setSelection,
  }: Props) => {
  const [isActive, setActive] = useState<boolean>(false);
  const ref = useRef<HTMLDivElement|null>(null);
  const [calendarActive, setCalendarActive] = useState<boolean>(false);
  const { t } = useTranslation();

  // For setting the date from datepicker / input fields
  const setFromWithClear = (from: string) => {
    setSelection(undefined);
    setFrom(from);
  };

  const setToWithClear = (to: string) => {
    setSelection(undefined);
    setTo(to);
  };

  const handleSelectionClick = (selected: string) => {
    if(selection === selected) {
      setSelection(undefined);
      setFrom(undefined);
      setTo(undefined);

      return;
    }

    switch(selected) {
      case selections.PAST_WEEK:
        setTo(format(new Date(), 'd.M.y'));
        setFrom(format(subWeeks(new Date(), 1), 'd.M.y'));
      break;
      case selections.PAST_MONTH:
        setTo(format(new Date(), 'd.M.y'));
        setFrom(format(subMonths(new Date(), 1), 'd.M.y'));
      break;
      case selections.PAST_YEAR:
        setTo(format(new Date(), 'd.M.y'));
        setFrom(format(subYears(new Date(), 1), 'd.M.y'));
      break;
    }

    setSelection(selected);
  };

  const transformDate = (date: string) => format(parse(date, 'd.M.y', new Date()), 'yyyy-MM-dd');

  const validateValues = useCallback(() =>  {
    const validateTo = () => {
      if(!to || !to.length) {
        return;
      }

      if(!isValidDate(to)) {
        return t('SEARCH:invalid-date');
      }
      if(isValidDate(from) && isValidDate(to)) {
        if(parse(from, 'd.M.y', new Date()) > parse (to, 'd.M.y', new Date())) {
          return t('SEARCH:to-less-than-from');
        }
      }
    };

    const validateFrom = () => {
      if(!from || !from.length) {
        return;
      }

      if(!isValidDate(from)) {
        return t('SEARCH:invalid-date');
      }
    };

    setErrors({
      from: validateFrom(),
      to: validateTo()
    });
  }, [t, from, setErrors, to]);

  const triggerQuery = useCallback(() => {
    if(queryFrom || queryTo) {
      const query: Query = {
        query: {
          range: {
            meeting_date: {}
          }
        }
      };

      if(queryFrom && isValidDate(queryFrom) && !errors.from) {
        query.query.range.meeting_date.gte = transformDate(queryFrom);
      }

      if(queryTo && isValidDate(queryTo) && !errors.to) {
        query.query.range.meeting_date.lte = transformDate(queryTo);
      }

      setQuery({
        query
      });
    }
    else {
      setQuery({
        query: null
      });
    }
  }, [queryFrom, queryTo, errors.from, errors.to, setQuery]);

  useEffect(() => {
    validateValues();
  }, [from, to, validateValues]);

  useEffect(() => {
    triggerQuery();
  }, [queryFrom, queryTo, triggerQuery]);

  useOutsideClick(ref, () => {
    setActive(false);
  });

  const getCollapsibleTitle = () => {
    if(calendarActive && isActive) {
      return <div className='DateSelect__title'><IconAngleLeft /><span>{t('DECISIONS:back')}</span></div>;
    }
    if((from && isValidDate(from)) || (to && isValidDate(to))) {
      let titleString = (from && isValidDate(from)) ? from : '';
      titleString += (to && isValidDate(to)) ? ` - ${to}` : ' -';
      return (
        <div className='DateSelect__title'>{titleString}</div>
      );
    }
    
      return <div className='DateSelect__title DateSelect__title--default'><IconCalendar /><span>{t('DECISIONS:choose-date')}</span></div>;
    
  };

  const getHandle = () => {
    if(!(calendarActive && isActive)) {
      return isActive ?
        <IconAngleUp /> :
        <IconAngleDown />;
    }
  };

  const handleControlClick = () => {
    if(calendarActive && isActive) {
      setCalendarActive(false);
    }
    else {
      setActive(!isActive);
    }
  };

  const collapsibleStyle: any = {};
  if(ref && ref.current) {
    collapsibleStyle.top = `${ref.current.clientHeight  }px`;
  }

  const renderField = () => (
    <>
      {calendarActive ?
        <div className='DateSelect__datepicker-wrapper'>
          <div className='DateSelect__datepicker-container'>
            <div className='DateSelect__date-fields-container'>
              <DateInput
                name='from'
                label={t('DECISIONS:start-date')}
                defaultValue={from}
                setDate={setFromWithClear}
                error={errors.from}
                onChange={setFrom}
                autoFocus
              />
              <IconMinus className='DateSelect__date-fields-divider' />
              <DateInput
                name='to'
                label={t('DECISIONS:end-date')}
                defaultValue={to}
                setDate={setToWithClear}
                error={errors.to}
                onChange={setTo}
              />
            </div>
            <DatePicker
              from={from}
              to={to}
              setTo={setToWithClear}
              setFrom={setFromWithClear}
            />
          </div>
          <Button className='DateSelect__inner-control' onClick={() => setActive (false)}>
            {t('DECISIONS:close')}
          </Button>
        </div> :
        <div className="DateSelect__predefined-ranges-wrapper">
          <div className='DateSelect__predefined-ranges-container'>
            <SelectionGroup>
              <Checkbox
                id='past_week'
                label={t('DECISIONS:past-week')}
                name='past_week'
                checked={selection === selections.PAST_WEEK}
                onClick={() => handleSelectionClick(selections.PAST_WEEK)}
              />
              <Checkbox
                id='past_month'
                label={t('DECISIONS:past-month')}
                name='past_month'
                checked={selection === selections.PAST_MONTH}
                onClick={() => handleSelectionClick(selections.PAST_MONTH)}
              />
              <Checkbox
                id='past_year'
                label={t('DECISIONS:past-year')}
                name='past_year'
                checked={selection === selections.PAST_YEAR}
                onClick={() => handleSelectionClick(selections.PAST_YEAR)}
              />
            </SelectionGroup>
          </div>
          <Button className='DateSelect__inner-control' onClick={() => setCalendarActive(true)}>
            {t('DECISIONS:choose-range')}
          </Button>
        </div>
      }
    </>
  );

  return (
    <div className={classNames(
        'decisions-search-date-select',
        'dateselect-wrapper',
        'decisions-search-form-element'
      )}
      ref={ref}
    >
      <label>{t('DECISIONS:date-select')}</label>
      <button
        type='button'
        className={classNames(
          'DateSelect__collapsible-control',
          'collapsible-element',
          { 'is-open': isActive }
        )}
        aria-controls={ariaControls}
        aria-expanded={isActive}
        onClick={handleControlClick}
      >
        <span className='DateSelect__collapsible-title'>{getCollapsibleTitle()}</span>
        <span className='DateSelect__collapsible-handle'>{getHandle()}</span>
      </button>
      {isActive &&
        <div
          className='DateSelect__collapsible-element collapsible-element--children'
          style={collapsibleStyle}
        >
          {renderField()}
        </div>
      }
    </div>
  );
};

export default DateSelect;
