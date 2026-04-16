import { useAtomValue, useSetAtom } from 'jotai';

import FilterButton from '@/react/common/FilterButton';
import SelectionsWrapper from '@/react/common/SelectionsWrapper';
import { Components } from '../enum/Components';
import { Events } from '../enum/Events';
import { resetStateAtom, type SearchState, searchStateAtom, submittedStateAtom, updateQueryAtom } from '../store';

type SelectOption = { label: string | undefined; value: string };

const clearDMEvent = new Event(Events.DECISIONS_CLEAR_SINGLE_DM);

export const SelectionsContainer = () => {
  const submittedState = useAtomValue(submittedStateAtom);
  const setState = useSetAtom(searchStateAtom);
  const upateQuery = useSetAtom(updateQueryAtom);
  const resetForm = useSetAtom(resetStateAtom);

  if (!submittedState) return null;

  const selections: React.ReactNode[] = [];

  const removeArrayItem = (key: keyof Pick<SearchState, 'category' | 'dm'>, value: string) => {
    const state = { ...submittedState };
    const existingItems = (state[key] as SelectOption[]).filter((item: SelectOption) => item.value !== value);
    state[key] = existingItems;
    setState(state);
    upateQuery(state);

    if (key === Components.DECISIONMAKER) {
      window.dispatchEvent(clearDMEvent);
    }
  };

  const unsetStateItem = (key: keyof Pick<SearchState, 'from' | 'to' | 's'>) => {
    const state = { ...submittedState };
    state[key] = undefined;
    setState(state);
    upateQuery(state);
  };

  const setStateItemToFalse = (key: keyof Pick<SearchState, 'bodies' | 'trustees'>) => {
    const state = { ...submittedState };
    state[key] = false;
    setState(state);
    upateQuery(state);
  };

  Object.entries({ ...submittedState })
    .filter(
      ([key]) =>
        !(
          [
            Components.TO,
            Components.FROM,
            Components.PAGE,
            Components.SEARCHBAR,
            Components.SORT,
          ] as (keyof SearchState)[]
        ).includes(key as keyof SearchState),
    )
    .forEach(([key, value], index) => {
      const typedKey = key as keyof SearchState;

      if (Array.isArray(value) && value.length && (key === Components.CATEGORY || key === Components.DECISIONMAKER)) {
        value.forEach((option: SelectOption) => {
          selections.push(
            <FilterButton
              key={`${key}-${option.value}`}
              clearSelection={() =>
                removeArrayItem(typedKey as keyof Pick<SearchState, 'category' | 'dm'>, option.value)
              }
              value={option.label || ''}
            />,
          );
        });
      } else if (
        typeof value === 'string' &&
        (key === Components.FROM || key === Components.TO || key === Components.SEARCHBAR)
      ) {
        selections.push(
          <FilterButton
            key={`${key}-${value}`}
            clearSelection={() => unsetStateItem(typedKey as keyof Pick<SearchState, 'from' | 'to' | 's'>)}
            value={value || ''}
          />,
        );
      } else if (
        typeof value === 'boolean' &&
        value === true &&
        (key === Components.BODIES || key === Components.TRUSTEES)
      ) {
        selections.push(
          <FilterButton
            // biome-ignore lint/suspicious/noArrayIndexKey: @todo UHF-12501
            key={`${key}-${value}-${index}`}
            clearSelection={() => setStateItemToFalse(typedKey as keyof Pick<SearchState, 'bodies' | 'trustees'>)}
            value={
              key === Components.BODIES
                ? Drupal.t('Decisions of decision-making bodies', {}, { context: 'Decisions search' })
                : Drupal.t('Decisions of office holders', {}, { context: 'Decisions search' })
            }
          />,
        );
      }
    });

  if (submittedState[Components.FROM] || submittedState[Components.TO]) {
    let titleString = '';
    if (submittedState[Components.FROM]) {
      titleString += submittedState[Components.FROM];
    }
    if (submittedState[Components.TO]) {
      titleString += (submittedState[Components.FROM] ? ' - ' : '- ') + submittedState[Components.TO];
    }

    selections.push(
      <FilterButton
        key={`date-${titleString}`}
        clearSelection={() => {
          const state = { ...submittedState };
          state[Components.FROM] = undefined;
          state[Components.TO] = undefined;
          setState(state);
          upateQuery(state);
        }}
        value={titleString}
      />,
    );
  }

  return (
    <SelectionsWrapper
      showClearButton={selections.length > 0 || (submittedState[Components.SEARCHBAR]?.length ?? 0) > 0}
      resetForm={resetForm}
    >
      {selections}
    </SelectionsWrapper>
  );
};
