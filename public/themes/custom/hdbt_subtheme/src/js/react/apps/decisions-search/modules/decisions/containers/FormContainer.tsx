import { useSetAtom } from 'jotai';
import { Button } from 'hds-react';

import { SearchBar } from '../components/SearchBar';
import { updateQueryAtom } from '../store';
import { CategoryFilter } from '../components/CategoryFilter';
import { DateFilter } from '../components/DateFilter';
import { DMSelect } from '../components/DMSelect';
import { SelectionsContainer } from './SelectionsContainer';
import { TrusteeFilter } from '../components/TrusteeFilter';
import { BodiesFilter } from '../components/BodiesFilter';

export const FormContainer = ({ url }: { url: string }) => {
  const updateQuery = useSetAtom(updateQueryAtom);

  return (
    // biome-ignore lint/a11y/useSemanticElements: @todo UHF-12501
    <form
      className='hdbt-search--react__form-container'
      onSubmit={(e) => {
        e.preventDefault();
        updateQuery();
      }}
      role='search'
    >
      <SearchBar url={url} />
      <div className='hdbt-search--react__dropdown-filters'>
        <DateFilter />
        <CategoryFilter />
        <DMSelect url={url} />
      </div>
      <div className='react-search__checkbox-filter-container'>
        <fieldset className='hdbt-search--react__fieldset'>
          <legend className='hdbt-search--react__legend'>
            {Drupal.t('Filters', {}, { context: 'Checkbox filters heading' })}
          </legend>
          <BodiesFilter />
          <TrusteeFilter />
        </fieldset>
      </div>
      <Button className='hdbt-search--react__submit-button' type='submit'>
        {Drupal.t(
          'Search',
          {},
          { context: 'React search: submit button label' },
        )}
      </Button>
      <SelectionsContainer />
    </form>
  );
};
