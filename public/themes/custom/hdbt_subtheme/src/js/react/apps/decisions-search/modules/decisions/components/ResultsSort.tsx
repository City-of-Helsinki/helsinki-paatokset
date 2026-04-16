import { Select } from 'hds-react';
import { useAtomValue, useSetAtom } from 'jotai';
import type OptionType from '@/common/types/OptionType';
import { defaultSelectTheme } from '@/react/common/constants/selectTheme';
import { getCurrentLanguage } from '@/react/common/helpers/GetCurrentLanguage';
import { SortOptions } from '../enum/SortOptions';
import { getSortAtom, setSortAtom } from '../store';

export const ResultsSort = () => {
  const value = useAtomValue(getSortAtom);
  const onChange = useSetAtom(setSortAtom);

  const options = [
    { label: Drupal.t('Most relevant first', {}, { context: 'Decisions search' }), value: SortOptions.RELEVANCE },
    { label: Drupal.t('Newest first', {}, { context: 'Decisions search' }), value: SortOptions.NEWEST },
    { label: Drupal.t('Oldest first', {}, { context: 'Decisions search' }), value: SortOptions.OLDEST },
  ];

  const handleChange = (selectedOptions: OptionType[]) => {
    onChange(selectedOptions);
  };

  return (
    <Select
      className='hdbt-search__dropdown'
      {...{ options, onChange: handleChange, value }}
      texts={{
        label: Drupal.t('Sort search results', {}, { context: 'HELfi Rekry job search' }),
        language: getCurrentLanguage(window.drupalSettings.path.currentLanguage),
      }}
      theme={defaultSelectTheme}
    />
  );
};
