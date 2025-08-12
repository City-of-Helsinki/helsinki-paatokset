import React from 'react';
import { Select } from 'hds-react';
import { useTranslation } from 'react-i18next';

import { Sort } from '../../enum/Sort';

type Props = {
  setSort: Function;
  selectedSort?: string;
};

const SortSelect = ({ setSort, selectedSort }: Props) => {
  const { t } = useTranslation();

  const getOptions = () => [
    {
      label: t('SEARCH:relevancy'),
      value: Sort.SCORE,
    },
    {
      label: t('SEARCH:most-recent-first'),
      value: Sort.DATE_DESC
    },
    {
      label: t('SEARCH:oldest-first'),
      value: Sort.DATE_ASC
    }
  ].map(option => ({
    ...option,
    selected: option.value === selectedSort,
  }));

  return (
    <Select
      className='decisions-search-sort-select'
      onChange={selectedOptions => {
        if (selectedOptions[0]) {
          setSort(selectedOptions[0].value);
        }
      }}
      options={getOptions()}
      texts={{
        label: t('SEARCH:sort')
      }}
    />
  );
};

export default SortSelect;