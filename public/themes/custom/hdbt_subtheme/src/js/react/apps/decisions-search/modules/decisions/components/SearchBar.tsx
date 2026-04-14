import { Search } from 'hds-react';
import { useAtomValue, useSetAtom } from 'jotai';

import { getElasticUrlAtom, getSearchTermAtom, setSearchTermAtom, updateQueryAtom } from '../store';
import { fetchSuggestions } from '../hooks/useGetSuggestions';
import { type ChangeEvent, useCallback, useState } from 'react';
import { defaultSearchInputTheme } from '@/react/common/constants/searchInputStyle';

export const SearchBar = () => {
  const searchTerm = useAtomValue(getSearchTermAtom);
  const setSearchTerm = useSetAtom(setSearchTermAtom);
  const url = useAtomValue(getElasticUrlAtom);
  const onSubmit = useSetAtom(updateQueryAtom);

  const [props] = useState({
    className: 'hdbt-search__filter hdbt-search__search-input',
    texts: {
      label: Drupal.t('Finnish keyword', {}, { context: 'Decisions search' }),
      language: window.drupalSettings.path.currentLanguage || 'fi',
      placeholder: Drupal.t('For example, Viikki', {}, { context: 'Decisions search' }),
      searchButtonAriaLabel: Drupal.t('Search', {}, { context: 'React search: submit button label' }),
    },
    theme: defaultSearchInputTheme,
  });

  const handleChange = useCallback(
    (e: ChangeEvent<HTMLInputElement>) => {
      setSearchTerm(e.target.value);
    },
    [setSearchTerm],
  );

  const handleSearch = useCallback((searchValue: string) => fetchSuggestions(searchValue, url), [url]);

  const handleSend = useCallback(() => onSubmit(), [onSubmit]);

  return (
    <Search
      {...props}
      onChange={handleChange}
      onSearch={handleSearch}
      onSend={handleSend}
      value={searchTerm?.toString() || ''}
    />
  );
};
