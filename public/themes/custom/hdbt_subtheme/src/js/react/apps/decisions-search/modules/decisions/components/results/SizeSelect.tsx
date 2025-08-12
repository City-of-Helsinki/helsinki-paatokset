import React from 'react';
import { Select } from 'hds-react';

type Props = {
  setSize: Function;
  selectedSize?: number;
};

const SortSelect = ({ setSize, selectedSize }: Props) => {
  const getOptions = () => [
    {
      label: '12',
      value: 12,
    },
    {
      label: '48',
      value: 48
    },
    {
      label: '96',
      value: 96
    }
  ].map(option => ({
    ...option,
    selected: option.value === selectedSize,
  }));

  return (
    <Select
      className='decisions-search-size-select'
      options={getOptions()}
      onChange={(selectedOptions) => {
        if (selectedOptions[0]) {
          setSize(selectedOptions[0].value);
        }
      }}
    />
  );
};

export default SortSelect;