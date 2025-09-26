import { atom } from 'jotai';
import { type estypes } from '@elastic/elasticsearch';

import { Components } from './enum/Components';
import { Events } from './enum/Events';
import { SortOptions } from './enum/SortOptions';
import { categoryToLabel } from '../../common/utils/CategoryToLabel';
import { PolicymakerIndex } from './enum/IndexFields';

const clearEvent = new Event(Events.DECISIONS_CLEAR_ALL);

const defaultState = {
  [Components.CATEGORY]: [],
  [Components.DATESELECTION]: undefined,
  [Components.DECISIONMAKER]: [],
  [Components.FROM]: undefined,
  [Components.PAGE]: 1,
  [Components.SEARCHBAR]: undefined,
  [Components.TO]: undefined,
  [Components.SORT]: SortOptions.RELEVANCE,
};

const initialParams = new URLSearchParams(window.location.search);
export const initialParamsAtom = atom(initialParams);

type aggType = {[string]: estypes.AggregationsBuckets};
export const aggsAtom = atom<aggType|undefined>(undefined, (get, set, aggs: aggType) => {
  const initialState = {...defaultState};

  Object.entries(defaultState).forEach(([key, value]) => {
    if (
      key === Components.CATEGORY &&
      initialParams.get(key)?.length &&
      aggs?.[key]?.buckets?.length
    ) {
      const selectedOptions = initialParams.get(key).split(',').filter(param => aggs[key].buckets.some((bucket: estypes.AggregationsAggregateBase) => bucket.key === param));
      if (selectedOptions.length) {
        initialState[key] = selectedOptions.map(option => ({label: categoryToLabel(option), value: option}));
      }
    }
    else if (
      key === Components.DECISIONMAKER &&
      initialParams.get(key)?.length &&
      aggs?.[key]?.buckets?.length
    ) {
      const paramsValue = initialParams.get(key)?.split(',');
      const selectedOptions = aggs[key].buckets.filter(bucket => paramsValue?.includes(bucket.key));
      if (selectedOptions.length) {
        initialState[key] = selectedOptions
          .filter(option => option[PolicymakerIndex.TITLE]?.buckets.length)
          .map(option => ({label: option[PolicymakerIndex.TITLE]?.buckets[0]?.key, value: option.key}));
      }
    }
    else {
      const param = initialParams.get(key);
      if (param !== null) {
        initialState[key] = param;
      }
    }
  });

  set(searchStateAtom, initialState);
  set(submittedStateAtom, initialState);
  set(aggsAtom, aggs);
});
const stateToURLParams = (state: typeof defaultState) => {
  const params = new URLSearchParams();
  Object.entries({...state}).forEach(([key, value]) => {
    if (Array.isArray(value) && value.length) {
      params.append(key, value.map(item => item.value));
    }
    else if (
      typeof value === 'string' ||
      typeof value === 'number'
    ) {
      params.set(key, value);
    }
  });
  const url = new URL(window.location.href);
  url.search = params.toString();
  window.history.pushState({}, '', url.toString());
};

export const searchStateAtom = atom<typeof defaultState|undefined>(undefined);
export const submittedStateAtom = atom<typeof defaultState|undefined>(undefined, (get, set, newValue: typeof defaultState) => {
  set(submittedStateAtom, newValue);
  set(searchStateAtom, newValue);
  stateToURLParams(newValue);
});
export const resetStateAtom = atom(null, (get, set) => {
  set(searchStateAtom, defaultState);
  set(submittedStateAtom, defaultState);
  window.dispatchEvent(clearEvent);
});

export const updateQueryAtom = atom(null, (get, set, newValue: typeof defaultState) => {
  const state = {...get(searchStateAtom), page: 1};
  set(submittedStateAtom, state);
});

export const getSearchTermAtom = atom((get) => {
  const state = {...get(searchStateAtom)};
  return state[Components.SEARCHBAR];
});

export const setSearchTermAtom = atom(null, (get, set, value: string|undefined) => {
  const state = {...get(searchStateAtom)};
  state[Components.SEARCHBAR] = value;
  set(searchStateAtom, state);
});

export const getPageAtom = atom((get) => {
  const state = {...get(submittedStateAtom)};
  return state[Components.PAGE];
});

export const setPageAtom = atom(null, (get, set, value: number) => {
  const state = {...get(submittedStateAtom)};
  state[Components.PAGE] = value;
  set(submittedStateAtom, state);
});

export const getCategoryAtom = atom((get) => {
  const state = {...get(searchStateAtom)};
  return state[Components.CATEGORY];
});

export const setCategoryAtom = atom(null, (get, set, value: Map<string, string>) => {
  const state = {...get(searchStateAtom)};
  state[Components.CATEGORY] = value;
  set(searchStateAtom, state);
});

export const getFromAtom = atom((get) => {
  const state = {...get(searchStateAtom)};
  return state[Components.FROM];
});

export const setFromAtom = atom(null, (get, set, value: string) => {
  const state = {...get(searchStateAtom)};
  state[Components.FROM] = value;
  set(searchStateAtom, state);
});

export const getToAtom = atom((get) => {
  const state = {...get(searchStateAtom)};
  return state[Components.TO];
});

export const setToAtom = atom(null, (get, set, value: string) => {
  const state = {...get(searchStateAtom)};
  state[Components.TO] = value;
  set(searchStateAtom, state);
});

export const getDateSelectionAtom = atom((get) => {
  const state = {...get(searchStateAtom)};
  return state[Components.DATESELECTION];
});

export const setDateSelectionAtom = atom(null, (get, set, value: string) => {
  const state = {...get(searchStateAtom)};
  state[Components.DATESELECTION] = value;
  set(searchStateAtom, state);
});

export const getDecisionMakersAtom = atom((get) => {
  const state = {...get(searchStateAtom)};
  return state[Components.DECISIONMAKER];
});

export const setDecisionMakersAtom = atom(null, (get, set, value: Map<string, string>) => {
  const state = {...get(searchStateAtom)};
  state[Components.DECISIONMAKER] = value;
  set(searchStateAtom, state);
});

export const getSortAtom = atom((get) => {
  const state = {...get(submittedStateAtom)};
  return state[Components.SORT];
});

export const setSortAtom = atom(null, (get, set, value: [{label: string, value: string}]) => {
  const state = {...get(submittedStateAtom)};
  state[Components.SORT] = value[0].value;
  set(submittedStateAtom, state);
});
