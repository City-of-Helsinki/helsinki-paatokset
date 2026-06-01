import type { estypes } from '@elastic/elasticsearch';
import { atom } from 'jotai';

declare const ELASTIC_DEV_URL: string | undefined;

import type { OptionType } from '@/common/types/OptionType';
import { addDays, addMonths, addYears, formatHDSDate } from '@/react/common/helpers/dateUtils';
import { categoryToLabel } from '../../common/utils/CategoryToLabel';
import { Components } from './enum/Components';
import { DateSelection } from './enum/DateSelection';
import { Events } from './enum/Events';
import { PolicymakerIndex } from './enum/IndexFields';
import { SortOptions } from './enum/SortOptions';

const clearEvent = new Event(Events.DECISIONS_CLEAR_ALL);

type SelectOption = { label: string | undefined; value: string };

export interface SearchState {
  [Components.CATEGORY]: SelectOption[];
  [Components.DECISIONMAKER]: SelectOption[];
  [Components.FROM]: string | undefined;
  [Components.BODIES]: boolean;
  [Components.PAGE]: number;
  [Components.SEARCHBAR]: string | undefined;
  [Components.SORT]: string;
  [Components.TO]: string | undefined;
  [Components.TRUSTEES]: boolean;
}

const defaultState: SearchState = {
  [Components.CATEGORY]: [],
  [Components.DECISIONMAKER]: [],
  [Components.FROM]: undefined,
  [Components.BODIES]: false,
  [Components.PAGE]: 1,
  [Components.SEARCHBAR]: undefined,
  [Components.SORT]: SortOptions.RELEVANCE,
  [Components.TO]: undefined,
  [Components.TRUSTEES]: false,
};

const initialParams = new URLSearchParams(window.location.search);
export const initialParamsAtom = atom(initialParams);

type aggType = { [key: string]: estypes.AggregationsAggregate };
const aggsBaseAtom = atom<aggType | undefined>(undefined);
export const aggsAtom = atom(
  (get) => get(aggsBaseAtom),
  (_get, set, aggs: aggType) => {
    const initialState = { ...defaultState };

    Object.entries(defaultState).forEach(([key, value]) => {
      if (
        key === Components.CATEGORY &&
        initialParams.get(key)?.length &&
        (aggs?.[key] as estypes.AggregationsStringTermsAggregate)?.buckets?.length
      ) {
        const categoryParam = initialParams.get(key);
        const categoryAgg = aggs[key] as estypes.AggregationsStringTermsAggregate;
        const buckets = categoryAgg.buckets as estypes.AggregationsStringTermsBucket[];
        const selectedOptions = categoryParam
          ?.split(',')
          .filter((param) => buckets.some((bucket) => bucket.key === param));
        if (selectedOptions?.length) {
          (initialState as Record<string, unknown>)[key] = selectedOptions.map((option) => ({
            label: categoryToLabel(option),
            value: option,
          }));
        }
      } else if (
        key === Components.DECISIONMAKER &&
        initialParams.get(key)?.length &&
        (aggs?.[key] as estypes.AggregationsStringTermsAggregate)?.buckets?.length
      ) {
        const paramsValue = initialParams.get(key)?.split(',');
        const decisionMakerAgg = aggs[key] as estypes.AggregationsStringTermsAggregate;
        const buckets = decisionMakerAgg.buckets as estypes.AggregationsStringTermsBucket[];
        const selectedOptions = buckets.filter((bucket) => paramsValue?.includes(bucket.key as string));
        if (selectedOptions.length) {
          (initialState as Record<string, unknown>)[key] = selectedOptions
            .filter(
              (option) =>
                (option as Record<string, estypes.AggregationsStringTermsAggregate>)[PolicymakerIndex.TITLE]?.buckets
                  ?.length,
            )
            .map((option) => {
              const titleAgg = (option as Record<string, estypes.AggregationsStringTermsAggregate>)[
                PolicymakerIndex.TITLE
              ];
              const titleBuckets = titleAgg?.buckets as estypes.AggregationsStringTermsBucket[] | undefined;
              return { label: titleBuckets?.[0]?.key as string | undefined, value: option.key as string };
            });
        }
      } else {
        const param = initialParams.get(key);
        if (
          typeof value === 'boolean' &&
          param === 'true' &&
          (key === Components.BODIES || key === Components.TRUSTEES)
        ) {
          initialState[key] = true;
        } else if (param !== null && Object.hasOwn(initialState, key)) {
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
    } else if (typeof value === 'boolean' && value === true) {
      params.set(key, 'true');
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
  window.dispatchEvent(clearEvent);
});

export const updateQueryAtom = atom(null, (get, set, _newValue?: typeof defaultState) => {
  const searchState = get(searchStateAtom);
  const submittedState = get(submittedStateAtom);
  const newState = searchState ? { ...searchState, [Components.PAGE]: 1 } : { ...defaultState, [Components.PAGE]: 1 };

  // Only update if state actually changed
  if (JSON.stringify(submittedState) !== JSON.stringify(newState)) {
    set(submittedStateAtom, newState);
  }
});

export const getSearchTermAtom = atom((get) => get(searchStateAtom)?.[Components.SEARCHBAR]);

export const setSearchTermAtom = atom(null, (get, set, value: string | undefined) => {
  const currentState = get(searchStateAtom);
  const state = currentState
    ? { ...currentState, [Components.SEARCHBAR]: value }
    : { ...defaultState, [Components.SEARCHBAR]: value };
  set(searchStateAtom, state);
});

export const getPageAtom = atom((get) => get(submittedStateAtom)?.[Components.PAGE] ?? 1);

export const setPageAtom = atom(null, (get, set, value: number) => {
  const currentState = get(submittedStateAtom);
  const state = currentState
    ? { ...currentState, [Components.PAGE]: value }
    : { ...defaultState, [Components.PAGE]: value };
  set(submittedStateAtom, state);
});

export const getCategoryAtom = atom((get) => get(searchStateAtom)?.[Components.CATEGORY] ?? []);

export const setCategoryAtom = atom(null, (get, set, value: SelectOption[]) => {
  const state = { ...get(searchStateAtom) } as SearchState;
  state[Components.CATEGORY] = value;
  set(searchStateAtom, state);
});

export const getFromAtom = atom((get) => get(searchStateAtom)?.[Components.FROM]);

export const setFromAtom = atom(null, (get, set, value: string | undefined) => {
  const currentState = get(searchStateAtom);
  const state = currentState
    ? { ...currentState, [Components.FROM]: value }
    : { ...defaultState, [Components.FROM]: value };
  set(searchStateAtom, state);
});

export const getToAtom = atom((get) => get(searchStateAtom)?.[Components.TO]);

export const setToAtom = atom(null, (get, set, value: string | undefined) => {
  const currentState = get(searchStateAtom);
  const state = currentState
    ? { ...currentState, [Components.TO]: value }
    : { ...defaultState, [Components.TO]: value };
  set(searchStateAtom, state);
});

export const getDateSelectionAtom = atom((get) => {
  const from = get(getFromAtom);
  const to = get(getToAtom);

  if (!from && !to) {
    return undefined;
  }

  const now = new Date();

  if (to !== formatHDSDate(now)) {
    return undefined;
  }

  switch (from) {
    case formatHDSDate(addDays(now, -7)):
      return DateSelection.PAST_WEEK;
    case formatHDSDate(addMonths(now, -1)):
      return DateSelection.PAST_MONTH;
    case formatHDSDate(addYears(now, -1)):
      return DateSelection.PAST_YEAR;
    default:
      return undefined;
  }
});

export const getDecisionMakersAtom = atom((get) => get(searchStateAtom)?.[Components.DECISIONMAKER] ?? []);

export const setDecisionMakersAtom = atom(null, (get, set, value: SelectOption[]) => {
  const state = { ...get(searchStateAtom) } as SearchState;
  state[Components.DECISIONMAKER] = value;
  set(searchStateAtom, state);
});

export const getSortAtom = atom((get) => get(submittedStateAtom)?.[Components.SORT] ?? SortOptions.RELEVANCE);

export const setSortAtom = atom(null, (get, set, value: OptionType[]) => {
  const currentState = get(submittedStateAtom);
  const state = currentState
    ? { ...currentState, [Components.SORT]: value[0]?.value.toString() ?? SortOptions.RELEVANCE }
    : { ...defaultState, [Components.SORT]: value[0]?.value.toString() ?? SortOptions.RELEVANCE };
  set(submittedStateAtom, state);
});

export const initializedAtom = atom<boolean>(false);

const ROOT_ID = 'paatokset_search';

const getElasticUrl = () => {
  const devUrl = typeof ELASTIC_DEV_URL !== 'undefined' ? ELASTIC_DEV_URL : '';
  if (devUrl) return devUrl;
  const rootElement = document.getElementById(ROOT_ID);
  return rootElement?.dataset.url || '';
};

export const getElasticUrlAtom = atom(getElasticUrl());

export const getTrusteesFilterAtom = atom((get) => get(searchStateAtom)?.[Components.TRUSTEES] ?? false);

export const setTrusteesFilterAtom = atom(null, (get, set, value: boolean) => {
  const currentState = get(searchStateAtom);
  const state = currentState
    ? { ...currentState, [Components.TRUSTEES]: value }
    : { ...defaultState, [Components.TRUSTEES]: value };
  set(searchStateAtom, state);
});

export const getBodiesFilterAtom = atom((get) => get(searchStateAtom)?.[Components.BODIES] ?? false);

export const setBodiesFilterAtom = atom(null, (get, set, value: boolean) => {
  const currentState = get(searchStateAtom);
  const state = currentState
    ? { ...currentState, [Components.BODIES]: value }
    : { ...defaultState, [Components.BODIES]: value };
  set(searchStateAtom, state);
});
