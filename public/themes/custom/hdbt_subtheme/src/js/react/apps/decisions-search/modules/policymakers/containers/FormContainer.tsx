import { Button } from 'hds-react';
import { useSetAtom } from 'jotai';

import { SearchBar } from '../components/SearchBar';
import { SectorFilter } from '../components/SectorFilter';
import { updateQueryAtom } from '../store';
import { SelectionsContainer } from './SelectionsContainer';

export const FormContainer = () => {
  const updateQuery = useSetAtom(updateQueryAtom);

  return (
    // biome-ignore lint/a11y/useSemanticElements: matches decisions pattern
    <form
      className='hdbt-search--react__form-container container'
      onSubmit={(e) => {
        e.preventDefault();
        updateQuery();
      }}
      role='search'
    >
      <SearchBar />
      <div className='hdbt-search--react__dropdown-filters'>
        <SectorFilter />
      </div>
      <Button className='hdbt-search--react__submit-button policymaker-submit-button' type='submit'>
        {Drupal.t('Search', {}, { context: 'React search: submit button label' })}
      </Button>
      <SelectionsContainer />
    </form>
  );
};
