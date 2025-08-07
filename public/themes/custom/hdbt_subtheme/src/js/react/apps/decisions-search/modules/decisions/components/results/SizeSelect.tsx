import React from 'react';
import { Select } from 'hds-react';

type Props = {
  setSize: Function
};

const SortSelect = ({ setSize }: Props) => (
    <Select
      className='decisions-search-size-select'
      label={null}
      options={[
        {
          label: '12',
          value: 12,
          selected: true,
        },
        {
          label: '48',
          value: 48
        },
        {
          label: '96',
          value: 96
        }
      ]}
      required
      onChange={({ value }: any) => setSize(value)}
    />
  );

export default SortSelect;