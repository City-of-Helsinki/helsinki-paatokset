import { Select } from 'hds-react';
import { useAtomValue, useSetAtom } from 'jotai';
import { type estypes } from '@elastic/elasticsearch';

import { aggsAtom, getCategoryAtom, setCategoryAtom } from '../store';
import { Components } from '../enum/Components';
import { defaultMultiSelectTheme } from '@/react/common/constants/selectTheme';
import { getCurrentLanguage } from '@/react/common/helpers/GetCurrentLanguage';
import { categoryToLabel } from '../../../common/utils/CategoryToLabel';

export const CategoryFilter = () => {
  const aggs = useAtomValue(aggsAtom);
  const value = useAtomValue(getCategoryAtom);
  const setCategories = useSetAtom(setCategoryAtom);

  const options = aggs?.[Components.CATEGORY]?.buckets
    .map((agg: estypes.Aggregation) => ({label: categoryToLabel(agg.key), value: agg.key}))
    .sort((a, b) => a.label.localeCompare(b.label));

  return (
    <Select
      className='hdbt-search__dropdown'
      disabled={!aggs}
      id={Components.CATEGORY}
      multiSelect
      noTags
      options={options}
      onChange={setCategories}
      texts={{
        label: Drupal.t('Topic', {}, { context: 'React search: topics filter' }),
        language: getCurrentLanguage(window.drupalSettings.path.currentLanguage),
        placeholder: Drupal.t('All topics', {}, { context: 'React search: topics filter' }),
      }}
      theme={defaultMultiSelectTheme}
      value={value}
    />
  );
};
