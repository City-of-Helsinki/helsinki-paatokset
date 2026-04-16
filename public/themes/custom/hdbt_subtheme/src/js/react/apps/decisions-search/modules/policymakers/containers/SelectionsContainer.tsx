import { useAtomValue, useSetAtom } from 'jotai';

import FilterButton from '@/react/common/FilterButton';
import SelectionsWrapper from '@/react/common/SelectionsWrapper';
import { Components } from '../enum/Components';
import {
  resetStateAtom,
  type SearchState,
  type SelectOption,
  searchStateAtom,
  submittedStateAtom,
  updateQueryAtom,
} from '../store';

export const SelectionsContainer = () => {
  const submittedState = useAtomValue(submittedStateAtom);
  const setState = useSetAtom(searchStateAtom);
  const updateQuery = useSetAtom(updateQueryAtom);
  const resetForm = useSetAtom(resetStateAtom);

  const selections: React.ReactNode[] = [];

  const removeArrayItem = (key: keyof Pick<SearchState, typeof Components.SECTOR>, value: string) => {
    if (!submittedState) return;

    const state = { ...submittedState };
    const existingItems = [...(state[key] as SelectOption[])];

    const index = existingItems.findIndex((item) => item.value === value);
    if (index > -1) {
      existingItems.splice(index, 1);
    }
    state[key] = existingItems;
    setState(state);
    updateQuery(state);
  };

  // Show selected sectors as removable tags
  const sectorSelections = submittedState?.[Components.SECTOR];
  if (sectorSelections?.length) {
    for (const option of sectorSelections) {
      selections.push(
        <FilterButton
          key={`${Components.SECTOR}-${option.value}`}
          clearSelection={() => removeArrayItem(Components.SECTOR, option.value)}
          value={option.label || option.value}
        />,
      );
    }
  }

  if (!selections.length) {
    return null;
  }

  return (
    <div className='decisions-search-selected-filters'>
      <div className='decisions-search-selected-filters__container'>
        <span className='decisions-search-selected-filters__filter-label'>
          {`${Drupal.t('Filters', {}, { context: 'Policymakers search' })}:`}
        </span>
        <SelectionsWrapper showClearButton={selections.length > 0} resetForm={resetForm}>
          {selections}
        </SelectionsWrapper>
      </div>
    </div>
  );
};
