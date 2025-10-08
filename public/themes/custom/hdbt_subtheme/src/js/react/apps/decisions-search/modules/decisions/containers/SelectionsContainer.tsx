import { useAtomValue, useSetAtom } from 'jotai';

import FilterButton from '@/react/common/FilterButton';
import { resetStateAtom, searchStateAtom, submittedStateAtom, updateQueryAtom } from '../store';
import SelectionsWrapper from '@/react/common/SelectionsWrapper';
import { Components, DATE_SELECTION } from '../enum/Components';
import { Events } from '../enum/Events';

const clearDMEvent = new Event(Events.DECISIONS_CLEAR_SINGLE_DM);

export const SelectionsContainer = () => {
  const submittedState = useAtomValue(submittedStateAtom);
  const setState = useSetAtom(searchStateAtom);
  const upateQuery = useSetAtom(updateQueryAtom);
  const resetForm = useSetAtom(resetStateAtom);

  const selections = [];

  const removeArrayItem = (key: string, value: string) => {
    const state = {...submittedState};
    const existingItem = [...state[key]];
    existingItem.splice(state[key].findIndex(item => item.value === value), 1);
    state[key] = existingItem;
    setState(state);
    upateQuery(state);

    if (key === Components.DECISIONMAKER) {
      window.dispatchEvent(clearDMEvent);
    }
  };

  const unsetStateItem = (key: string) => {
    const state = {...submittedState};
    state[key] = undefined;
    setState(state);
    upateQuery(state);
  };

  Object.entries({...submittedState})
    .filter(([key]) => ![Components.TO, Components.FROM, Components.PAGE, Components.SEARCHBAR, Components.SORT].includes(key))
    .forEach(([key, value], index) => {
      if (Array.isArray(value) && value.length) {
        value.forEach((option: string) => {
          selections.push(
            <FilterButton
              key={`${key}-${option.value}`}
              clearSelection={() => removeArrayItem(key, option.value)}
              value={option.label}
            />
          );
        });
      }
      else if (typeof value === 'string') {
        selections.push(
          <FilterButton
            key={`${key}-${value}`}
            clearSelection={() => unsetStateItem(key)}
            value={value}
          />
        );
      }
    }
  );

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
          const state = {...submittedState};
          state[Components.FROM] = undefined;
          state[Components.TO] = undefined;
          state[DATE_SELECTION] = undefined;
          setState(state);
          upateQuery(state);
        }}
        value={titleString}
      />
    );
  }

  return (
    <SelectionsWrapper
      showClearButton={selections.length || submittedState[Components.SEARCHBAR]?.length}
      resetForm={resetForm}
    >
      {selections}
    </SelectionsWrapper>
  );
};
