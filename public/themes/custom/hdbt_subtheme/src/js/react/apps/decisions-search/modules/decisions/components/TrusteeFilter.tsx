import { Checkbox } from 'hds-react';
import { useAtomValue, useSetAtom } from 'jotai';

import { Components } from '../enum/Components';
import { getTrusteesFilterAtom, setTrusteesFilterAtom } from '../store';
import { defaultCheckboxStyle } from '@/react/common/constants/checkboxStyle';

export const TrusteeFilter = () => {
  const value = useAtomValue(getTrusteesFilterAtom);
  const setValue = useSetAtom(setTrusteesFilterAtom);

  return (
    <Checkbox
      checked={value}
      className='hdbt-search--react__checkbox'
      id={Components.TRUSTEES}
      label={Drupal.t(
        'Decisions of office holders',
        {},
        { context: 'Decisions search' },
      )}
      name={Components.TRUSTEES}
      onChange={(e) => setValue(e.target.checked)}
      style={defaultCheckboxStyle}
      value={Components.TRUSTEES}
    />
  );
};
