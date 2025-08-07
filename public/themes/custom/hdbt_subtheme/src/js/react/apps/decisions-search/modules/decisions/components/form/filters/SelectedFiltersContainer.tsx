import React from 'react';
import { SelectedFilters } from '@appbaseio/reactivesearch';
import { IconCross } from 'hds-react';
import { useTranslation } from 'react-i18next';

import { Option, Options } from '../../../types/types';

type Props = {
  categories: Array<Option>,
  setCategories: Function,
  dms: Options|null,
  setDms: Function,
  to: any,
  setTo: Function,
  from: any,
  setFrom: Function,
  setSelection: Function,
}

const SelectedFiltersContainer = ({ categories, setCategories, dms, setDms, from, setFrom, to, setTo, setSelection }: Props) => {
  const { t } = useTranslation();

  if(categories.length <= 0 && !dms && !to && !from) {
    return null;
  }

  const getCategoryFilters = () => {
    const deleteCategory = (value: string) => {
      let current = [...categories];
      current = current.filter((category) => {
        if (category.value === value) {
          return false;
        }
        return true;
      });
      setCategories(current, true);
    };

    return categories.map(category => (
      <button
        className='decisions-search-selected-filters-container__filter'
        key={category.value}
        onClick={() => deleteCategory(category.value)}
      >
        {category.label}
        <IconCross />
      </button>
    ));
  };

  const getDmFilters = () => {
    if(!dms) {
      return null;
    }

    const deleteSingleDm = (dm: any) => {
      setDms(dms.filter(_dm => _dm.value !== dm), true);
    };

    return dms.map((dm: Option) => (
        <button
          className="decisions-search-selected-filters-container__filter"
          key={dm.value}
          onClick={() => deleteSingleDm(dm.value)}
        >
          {dm.label}
          <IconCross />
        </button>
      ));
  };

  const clearDms = () => {
    setDms(null, true);
  };

  const getDateFilter = () => {
    if(!to && !from) {
      return null;
    }

    const deleteDateQuery = () => {
      setTo(null, true);
      setFrom(null, true);
      setSelection(null);
    };

    return (
      <button
        className='decisions-search-selected-filters-container__filter'
        key='date'
        onClick={() => deleteDateQuery()}
      >
        { from } - { to }
        <IconCross />
      </button>
    );
  };
  
  return (
    <div className='decisions-search-selected-filters-container form-element container'>
      <SelectedFilters
        render={() => (
            <div className='decisions-search-selected-filters-container__container'>
              {getDateFilter()}
              {getCategoryFilters()}
              {getDmFilters()}
              {dms && dms.length > 0 &&
                <button
                  className='decisions-search-selected-filters-container__clear-filters'
                  onClick={() => {
                    setCategories([], true);
                    clearDms();
                    setFrom(null, true);
                    setTo(null, true);
                    setSelection(null);
                  }}
                >
                  <IconCross />
                  {t('SEARCH:clear-all')}
                </button>
              }

            </div>
          )}
      />
    </div>
  );
};

export default SelectedFiltersContainer;
