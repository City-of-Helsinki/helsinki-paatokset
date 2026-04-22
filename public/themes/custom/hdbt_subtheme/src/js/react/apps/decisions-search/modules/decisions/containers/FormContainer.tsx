import { Button } from 'hds-react';
import { useSetAtom } from 'jotai';
import { BodiesFilter } from '../components/BodiesFilter';
import { CategoryFilter } from '../components/CategoryFilter';
import { DateFilter } from '../components/DateFilter';
import { DMSelect } from '../components/DMSelect';
import { SearchBar } from '../components/SearchBar';
import { TrusteeFilter } from '../components/TrusteeFilter';
import { updateQueryAtom } from '../store';
import { SelectionsContainer } from './SelectionsContainer';

export const FormContainer = () => {
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
      <SearchBar />
      <div className='hdbt-search--react__dropdown-filters'>
        <DateFilter />
        <CategoryFilter />
        <DMSelect />
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
        {Drupal.t('Search', {}, { context: 'React search: submit button label' })}
      </Button>
      <SelectionsContainer />
    </form>
  );
};
