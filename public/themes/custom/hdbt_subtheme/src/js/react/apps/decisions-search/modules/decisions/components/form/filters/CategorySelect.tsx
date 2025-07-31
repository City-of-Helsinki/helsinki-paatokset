import React, { useEffect, useCallback } from 'react';
import { Select } from 'hds-react';
import { useTranslation } from 'react-i18next';
import { Option } from '../../../types/types';

import classNames from 'classnames';

type Props = {
  aggregations: any,
  setQuery: Function,
  setValue: Function,
  value: Array<Option>,
  queryValue: Array<Option>
}

const CategorySelect = ({ aggregations, setQuery, setValue, value, queryValue }: Props) => {
  let categories: Array<any> = [];
  const { t } = useTranslation();

  if(
    aggregations &&
    aggregations.top_category_code &&
    aggregations.top_category_code.buckets.length
    ) {
    categories = aggregations.top_category_code.buckets.map((category: any) => ({
      label: t("CATEGORIES:" + category.key),
      value: category.key
    }));
  }

  const triggerQuery = useCallback(() => {
    if(queryValue.length) {
      setQuery({
        query: {
          terms: {
            top_category_code: queryValue
          }
        },
        value: queryValue
      });
    }
    else {
      setQuery({
        query: null,
        values: []
      });
    }
  }, [queryValue, setQuery]);

  useEffect(() => {
    triggerQuery();
  }, [queryValue, setQuery, triggerQuery])

  const onChange = (categories: Array<any>) => {
    const values = categories.map((category) => {
      return {value: category.value, label: category.label};
    });
    setValue(values);
  }

  const formattedValue: Array<any> = value.map((category) => {
    return {value: category.value, label: category.label};
  });

  return (
    <Select
      multiselect
      className={classNames(
        'decisions-search-multiselect',
        'decisions-search-form-element',
      )}
      label={t('DECISIONS:topic')}
      placeholder={t('DECISIONS:choose-topic')}
      options={categories}
      value={formattedValue}
      clearButtonAriaLabel='Clear all selections'
      selectedItemRemoveButtonAriaLabel={`Remove value`}
      onChange={onChange}
    />
  );
}

export default CategorySelect;
