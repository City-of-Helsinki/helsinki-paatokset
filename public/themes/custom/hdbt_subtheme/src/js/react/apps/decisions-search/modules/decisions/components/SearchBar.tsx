import { SearchInput } from 'hds-react';
import { useAtomValue, useSetAtom } from 'jotai';

import {
  getSearchTermAtom,
  setSearchTermAtom,
  updateQueryAtom,
} from '../store';
import { useGetSuggestions } from '../hooks/useGetSuggestions';

export const SearchBar = ({ url }: { url: string }) => {
  const searchTerm = useAtomValue(getSearchTermAtom);
  const setSearchTerm = useSetAtom(setSearchTermAtom);
  const suggestions = useGetSuggestions(searchTerm, url);
  const onSubmit = useSetAtom(updateQueryAtom);

  return (
    <SearchInput
      className='hdbt-search__filter hdbt-search--react__text-field'
      getSuggestions={() => suggestions}
      label={Drupal.t('Finnish keyword', {}, { context: 'Decisions search' })}
      onChange={(inputValue) => setSearchTerm(inputValue)}
      onSubmit={onSubmit}
      placeholder={Drupal.t(
        'For example, Viikki',
        {},
        { context: 'Decisions search' },
      )}
      searchButtonAriaLabel={Drupal.t(
        'Search',
        {},
        { context: 'React search: submit button label' },
      )}
      suggestionKeyField='value'
      suggestionLabelField='value'
      value={searchTerm || ''}
    />
  );
};
