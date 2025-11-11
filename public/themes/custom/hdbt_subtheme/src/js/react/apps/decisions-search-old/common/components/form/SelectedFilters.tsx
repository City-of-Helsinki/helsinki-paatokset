import React from 'react';
import { SelectedFilters } from '@appbaseio/reactivesearch';
import { IconCross } from 'hds-react';
import { useTranslation } from 'react-i18next';
import classNames from 'classnames';

import useWindowDimensions from '../../../hooks/useWindowDimensions';

type SelectedFilter = {
  value: string,
  type: string,
  deleteFilter: Function,
};

type Props = {
  filters?: SelectedFilter[],
  clearAll: Function
};

const SelectedFiltersContainer = ({ filters, clearAll }: Props) => {
  const { t } = useTranslation();
  const { width } = useWindowDimensions();

  if(!filters || filters.length <= 0) {
    return null;
  }

  return (
    <div className='decisions-search-selected-filters'>
      <SelectedFilters
        render={() => {
          const filterButtons = filters.map(filter => (
            <button
              className='decisions-search-selected-filters__filter'
              key={filter.value}
              type='button'
              onClick={() => filter.deleteFilter(filter.value, filter.type)}
            >
              {filter.value}
              <IconCross />
            </button>
          ));

          return (
            <div className='decisions-search-selected-filters__container'>
              {
                width >= 1200 &&
                <span className='decisions-search-selected-filters__filter-label'>
                  {`${t('SEARCH:filters')  }:`}
                </span>
              }
              {filterButtons}
              <button
                className={classNames(
                  'decisions-search-selected-filters__filter',
                  'decisions-search-selected-filters__clear-filters'
                )}
                onClick={() => clearAll()}
                type='button'
              >
                <IconCross />
                {t('SEARCH:clear-all')}
              </button>
            </div>
          );
        }}
      />
    </div>
  );
};

export default SelectedFiltersContainer;
