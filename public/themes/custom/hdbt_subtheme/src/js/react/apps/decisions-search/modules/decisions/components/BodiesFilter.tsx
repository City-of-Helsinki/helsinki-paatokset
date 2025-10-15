import { Checkbox } from 'hds-react';
import { useAtomValue, useSetAtom } from 'jotai';

import { Components } from '../enum/Components';
import { getBodiesFilterAtom, setBodiesFilterAtom } from '../store';
import { defaultCheckboxStyle } from '@/react/common/constants/checkboxStyle';

export const BodiesFilter = () => {
  const value = useAtomValue(getBodiesFilterAtom);
  const setValue = useSetAtom(setBodiesFilterAtom);
  
  return <Checkbox
    checked={value}
    className='hdbt-search--react__checkbox'
    id={Components.BODIES}
    label={Drupal.t('Decisions of decision-making bodies', {}, { context: 'Decisions search' })}
    name={Components.BODIES}
    onChange={e => setValue(e.target.checked)}
    style={defaultCheckboxStyle}
    value={Components.BODIES}
  />;
};
