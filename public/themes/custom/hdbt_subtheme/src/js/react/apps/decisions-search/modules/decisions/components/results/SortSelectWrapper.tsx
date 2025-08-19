import React from 'react';
import { Select } from 'hds-react';
import { ReactiveComponent } from '@appbaseio/reactivesearch';

import SortSelect from './SortSelect';

type Props = {
  setSort: Function
}

const SortSelectWrapper = ({ setSort }: Props) => (
    <ReactiveComponent
      componentId='sort-select'
      render={({ aggregations, setQuery }) => (
        null
      )}
    />
  );

export default SortSelectWrapper;