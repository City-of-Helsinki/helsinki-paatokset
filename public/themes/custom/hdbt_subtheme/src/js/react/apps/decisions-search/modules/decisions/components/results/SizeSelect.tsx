import React from 'react';
import { Select } from 'hds-react';

import './SizeSelect.scss';

type Props = {
  setSize: Function
};

const SortSelect = ({ setSize }: Props) => {
  return (
    <Select
      className='SizeSelect'
      style={{
        padding: '2px'
      }}
      label={null}
      defaultValue={{
        label: '12',
        value: 12
      }}
      options={[
        {
          label: '12',
          value: 12
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
      onChange={({value}: any) => setSize(value)}
    />
  );
}

export default SortSelect;