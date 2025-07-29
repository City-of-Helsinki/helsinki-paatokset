import { useEffect } from 'react';
import { Combobox as HDSCombobox } from 'hds-react';
import classNames from 'classnames';

import styles from './Combobox.module.scss';
import formStyles from '../../styles/Form.module.scss';

type Props = {
  aggregations: any,
  aggregationKey: string,
  value: string[],
  setValue: Function,
  setQuery: Function,
  queryValue: Array<string>,
  label?: string,
  placeholder?: string
}

const Combobox = ({
  aggregations,
  aggregationKey,
  value,
  setValue,
  setQuery,
  queryValue,
  label,
  placeholder
}: Props) => {

  useEffect(() => {
    if(queryValue.length) {
      setQuery({
        query: {
          terms: {
            [aggregationKey]: queryValue
          }
        },
        value: queryValue
      })
    }
    else {
      setQuery({
        query: null,
        values: []
      })
    }
  }, [queryValue, aggregationKey, setQuery]);

  let options: Array<any> = [];

  if(
    aggregations &&
    aggregations[aggregationKey] &&
    aggregations[aggregationKey].buckets.length
  ) {
    options = aggregations[aggregationKey].buckets.map((option: any) => ({
      label: option.key,
      value: option.key
    }));
  }

  const onChange = (fields: Array<any>) => {
    const values = fields.map(field => field.value);
    setValue(values);
  };

  const formattedValue: Array<any> = value.map((field) => {
    return {value: field, label: field}
  });

  return (
    <HDSCombobox
      className={classNames(
        styles.Combobox,
        formStyles['form-element']
      )}
      label={label}
      placeholder={placeholder}
      options={options}
      value={formattedValue}
      multiselect={true}
      clearButtonAriaLabel='Clear all selections'
      selectedItemRemoveButtonAriaLabel={`Remove value`}
      toggleButtonAriaLabel='Toggle menu'
      onChange={onChange}
    />
  )
}

export default Combobox;
