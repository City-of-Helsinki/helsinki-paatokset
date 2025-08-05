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
      label={t('SEARCH:sort')}
      defaultValue={{
        label: t('SEARCH:relevancy'),
        value: Sort.SCORE
      }}
      options={[
        {
          label: t('SEARCH:relevancy'),
          value: Sort.SCORE
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
      onChange={({ value }: any) => setSort(value)}
    />
  );
};

export default SortSelect;