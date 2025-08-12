import { useEffect } from 'react';
import { Select } from 'hds-react';
import classNames from 'classnames';

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
      });
    }
    else {
      setQuery({
        query: null,
        values: []
      });
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

  const formattedValue: Array<any> = value.map((field) => ({ value: field, label: field }));

  return (
    <Select
      className={classNames(
        'decisions-search-combobox',
        'decisions-search-form-element',
      )}
      multiSelect
      noTags
      onChange={onChange}
      options={options}
      texts={{
        label,
        placeholder,
      }}
      value={formattedValue}
    />
  );
};

export default Combobox;
