import type { estypes } from '@elastic/elasticsearch';
import { atom } from 'jotai';
import { Components } from './enum/Components';

const ROOT_ID = 'paatokset_search';

const getElasticUrl = () => {
  const rootElement = document.getElementById(ROOT_ID);
  return rootElement?.dataset.url || '';
};

export const getElasticUrlAtom = atom(getElasticUrl());

type SelectOption = { label: string | undefined; value: string };

interface SearchState {
  [Components.SEARCHBAR]: string | undefined;
  [Components.SECTOR]: SelectOption[];
  [Components.PAGE]: number;
}

const defaultState: SearchState = {
  [Components.SEARCHBAR]: undefined,
  [Components.SECTOR]: [],
  [Components.PAGE]: 1,
};

const initialParams = new URLSearchParams(window.location.search);
export const initialParamsAtom = atom(initialParams);

// Check if URL has search-related params (s or sector, not just page)
const hasSearchParams = initialParams.has(Components.SEARCHBAR) || initialParams.has(Components.SECTOR);

// Track if results should be shown
export const searchActiveAtom = atom<boolean>(hasSearchParams);

type aggType = { [key: string]: estypes.AggregationsAggregate };
const aggsBaseAtom = atom<aggType | undefined>(undefined);
export const aggsAtom = atom(
  (get) => get(aggsBaseAtom),
  (_get, set, aggs: aggType) => {
    const initialState = { ...defaultState };

    Object.entries(defaultState).forEach(([key, value]) => {
      if (
        key === Components.SECTOR &&
        initialParams.get(key)?.length &&
        (aggs?.[key] as estypes.AggregationsStringTermsAggregate)?.buckets?.length
      ) {
        const sectorParam = initialParams.get(key);
        const sectorAgg = aggs[key] as estypes.AggregationsStringTermsAggregate;
        const buckets = sectorAgg.buckets as estypes.AggregationsStringTermsBucket[];
        const selectedOptions = sectorParam
          ?.split(',')
          .filter((param) => buckets.some((bucket) => bucket.key === param));
        if (selectedOptions?.length) {
          (initialState as Record<string, unknown>)[key] = selectedOptions.map((option) => ({
            label: option,
            value: option,
          }));
        }
      } else {
        const param = initialParams.get(key);
        if (typeof value === 'number' && param !== null) {
          const parsedValue = Number.parseInt(param, 10);
          if (!Number.isNaN(parsedValue)) {
            (initialState as Record<string, unknown>)[key] = parsedValue;
          }
        } else if (param !== null) {
          (initialState as Record<string, unknown>)[key] = param;
        }
      }
    });

    set(searchStateAtom, initialState);
    set(submittedStateAtom, initialState);
    set(aggsBaseAtom, aggs);
  },
);

const stateToURLParams = (state: typeof defaultState) => {
  const params = new URLSearchParams();
  Object.entries({ ...state }).forEach(([key, value]) => {
    if (Array.isArray(value) && value.length) {
      params.append(key, value.map((item: { value: string }) => item.value).join(','));
    } else if (typeof value === 'string' || typeof value === 'number') {
      params.set(key, String(value));
    }
  });
  const url = new URL(window.location.href);
  const oldUrlString = url.toString();
  url.search = params.toString();

  if (oldUrlString === url.toString()) {
    return;
  }

  window.history.pushState({}, '', url.toString());
};

export const searchStateAtom = atom<SearchState | undefined>(undefined);
const submittedBaseAtom = atom<SearchState | undefined>(undefined);
export const submittedStateAtom = atom(
  (get) => get(submittedBaseAtom),
  (_get, set, newValue: SearchState) => {
    set(submittedBaseAtom, newValue);
    set(searchStateAtom, newValue);
    stateToURLParams(newValue);
  },
);

export const resetStateAtom = atom(null, (_get, set) => {
  set(searchStateAtom, defaultState);
  set(submittedStateAtom, defaultState);
});

export const updateQueryAtom = atom(null, (get, set, _newValue?: typeof defaultState) => {
  const searchState = get(searchStateAtom);
  const submittedState = get(submittedStateAtom);
  const newState = { ...searchState, [Components.PAGE]: 1 };

  // Activate search on first submit
  if (!get(searchActiveAtom)) {
    set(searchActiveAtom, true);
  }

  // Only update if state actually changed
  if (JSON.stringify(submittedState) !== JSON.stringify(newState)) {
    set(submittedStateAtom, newState);
  }
});

export const getSearchTermAtom = atom((get) => {
  const state = { ...get(searchStateAtom) };
  return state[Components.SEARCHBAR];
});

export const setSearchTermAtom = atom(null, (get, set, value: string | undefined) => {
  const state = { ...get(searchStateAtom) };
  state[Components.SEARCHBAR] = value;
  set(searchStateAtom, state);
});

export const getPageAtom = atom<number>((get) => {
  const state = get(submittedStateAtom);
  return state?.[Components.PAGE] ?? 1;
});

export const setPageAtom = atom(null, (get, set, value: number) => {
  const state = { ...get(submittedStateAtom) };
  state[Components.PAGE] = value;
  set(submittedStateAtom, state);
});

export const getSectorAtom = atom((get) => {
  const state = { ...get(searchStateAtom) };
  return state[Components.SECTOR];
});

export const setSectorAtom = atom(null, (get, set, value: SelectOption[]) => {
  const state = { ...get(searchStateAtom) } as SearchState;
  state[Components.SECTOR] = value;
  set(searchStateAtom, state);
});

export const initializedAtom = atom<boolean>(false);
