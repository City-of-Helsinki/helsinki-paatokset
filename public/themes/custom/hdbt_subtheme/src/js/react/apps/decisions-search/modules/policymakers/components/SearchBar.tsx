import { Search } from 'hds-react';
import { useAtomValue, useSetAtom } from 'jotai';

import { getElasticUrlAtom, getSearchTermAtom, setSearchTermAtom, updateQueryAtom } from '../store';
import { fetchSuggestions } from '../hooks/useGetSuggestions';
import { defaultSearchInputTheme } from '@/react/common/constants/searchInputStyle';
import { type ChangeEvent, useCallback, useState } from 'react';
import { useEffect, useRef } from 'react';

export const SearchBar = () => {
  const searchTerm = useAtomValue(getSearchTermAtom);
  const setSearchTerm = useSetAtom(setSearchTermAtom);
  const url = useAtomValue(getElasticUrlAtom);
  const onSubmit = useSetAtom(updateQueryAtom);

  const initialSearchTermRef = useRef(searchTerm);
  useEffect(() => {
    const initial = initialSearchTermRef.current;
    if (initial) {
      setSearchTerm(initial);
    }
  }, [setSearchTerm]);

  const [props] = useState({
    className: 'hdbt-search__filter hdbt-search__search-input',
    hideSubmitButton: true,
    texts: {
      label: Drupal.t(
        'Which body, office holder or councillor are you looking for?',
        {},
        { context: 'Policymakers search' },
      ),
      language: window.drupalSettings.path.currentLanguage || 'fi',
      placeholder: Drupal.t('Search with a Finnish keyword, eg. pormestari', {}, { context: 'Policymakers search' }),
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
