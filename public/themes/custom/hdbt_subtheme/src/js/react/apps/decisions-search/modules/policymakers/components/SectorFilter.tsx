import type { estypes } from '@elastic/elasticsearch';
import { Select } from 'hds-react';
import { useAtomValue, useSetAtom } from 'jotai';
import { defaultMultiSelectTheme } from '@/react/common/constants/selectTheme';
import { getCurrentLanguage } from '@/react/common/helpers/GetCurrentLanguage';
import { Components } from '../enum/Components';
import { aggsAtom, getSectorAtom, setSectorAtom } from '../store';

export const SectorFilter = () => {
  const aggs = useAtomValue(aggsAtom);
  const value = useAtomValue(getSectorAtom);
  const setSectors = useSetAtom(setSectorAtom);

  const sectorAgg = aggs?.[Components.SECTOR] as estypes.AggregationsStringTermsAggregate | undefined;
  const buckets = sectorAgg?.buckets as estypes.AggregationsStringTermsBucket[] | undefined;

  const options = buckets
    ?.map((bucket) => ({
      label: bucket.key as string,
      value: bucket.key as string,
    }))
    .sort((a, b) => a.label.localeCompare(b.label)) as { label: string; value: string }[] | undefined;

  return (
    <Select
      className='hdbt-search__dropdown policymaker-dropdown'
      disabled={!aggs}
      id={Components.SECTOR}
      multiSelect
      noTags
      options={options}
      onChange={setSectors}
      texts={{
        label: Drupal.t('Division', {}, { context: 'Policymakers search' }),
        language: getCurrentLanguage(window.drupalSettings.path.currentLanguage),
        placeholder: Drupal.t('Choose division', {}, { context: 'Policymakers search' }),
      }}
      theme={defaultMultiSelectTheme}
      value={value}
    />
  );
};
