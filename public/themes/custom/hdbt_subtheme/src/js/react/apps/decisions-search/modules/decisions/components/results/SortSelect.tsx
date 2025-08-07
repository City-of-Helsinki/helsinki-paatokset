import React from 'react';
import { Select } from 'hds-react';
import { useTranslation } from 'react-i18next';

import { Sort } from '../../enum/Sort';

type Props = {
  setSort: Function
};

const SortSelect = ({ setSort }: Props) => {
  const { t } = useTranslation();

  return (
    <Select
      className='decisions-search-sort-select'
      onChange={({ value }: any) => setSort(value)}
      options={[
        {
          label: t('SEARCH:relevancy'),
          value: Sort.SCORE,
          selected: true,
        },
        {
          label: t('SEARCH:most-recent-first'),
          value: Sort.DATE_DESC
        },
        {
          label: t('SEARCH:oldest-first'),
          value: Sort.DATE_ASC
        }
      ]}
      texts={{
        label: t('SEARCH:sort')
      }}
    />
  );
};

export default SortSelect;