import { SearchInput } from 'hds-react';
import { useAtomValue, useSetAtom } from 'jotai';

import { getElasticUrlAtom, getSearchTermAtom, setSearchTermAtom, updateQueryAtom } from '../store';
import { useGetSuggestions } from '../hooks/useGetSuggestions';

export const SearchBar = () => {
  const searchTerm = useAtomValue(getSearchTermAtom);
  const setSearchTerm = useSetAtom(setSearchTermAtom);
  const url = useAtomValue(getElasticUrlAtom);
  const suggestions = useGetSuggestions(searchTerm, url);
  const onSubmit = useSetAtom(updateQueryAtom);

  return (
    <SearchInput
      className='hdbt-search__filter hdbt-search--react__text-field'
      getSuggestions={() => suggestions}
      label={Drupal.t(
        'Which body, office holder or councillor are you looking for?',
        {},
        { context: 'Policymakers search' },
      )}
      onChange={(inputValue) => setSearchTerm(inputValue)}
      onSubmit={onSubmit}
      placeholder={Drupal.t('Search with a Finnish keyword, eg. pormestari', {}, { context: 'Policymakers search' })}
      searchButtonAriaLabel={Drupal.t('Search', {}, { context: 'React search: submit button label' })}
      suggestionKeyField='value'
      suggestionLabelField='value'
      value={searchTerm || ''}
    />
  );
};
