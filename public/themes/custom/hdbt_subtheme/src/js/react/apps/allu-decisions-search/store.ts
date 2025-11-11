import { atom } from 'jotai';
import { RESET } from 'jotai/utils';
import { matchTypeValueFromLabel } from './helpers';

import type { Selections } from './types/Selections';

const initalUrlParams = new URLSearchParams(window.location.search);
const initialParams = {
  end: initalUrlParams.get('end') || undefined,
  page: initalUrlParams.get('page') || undefined,
  q: initalUrlParams.get('q') || undefined,
  start: initalUrlParams.get('start') || undefined,
  type:
    initalUrlParams
      .getAll('type')
      .map((label) => ({ label, value: matchTypeValueFromLabel(label) })) ||
    undefined,
};

export const selectionsAtom = atom<Selections>(initialParams);

export const setSelectionsAtom = atom(
  null,
  (
    _get,
    set,
    value: Partial<Selections> | typeof RESET,
    partial: boolean = false,
  ) => {
    if (value === RESET) {
      set(selectionsAtom, {});
      return;
    }

    set(selectionsAtom, (currentValue) =>
      partial ? { ...currentValue, ...value } : value,
    );
  },
);

export const getPageAtom = atom((get) => {
  const selections = get(selectionsAtom);

  return selections?.page;
});

const selectionsToURLParams = (currentParams: Selections) => {
  const params = new URLSearchParams();

  Object.entries(currentParams).forEach((entry) => {
    const [key, value] = entry;

    if (value && Array.isArray(value) && value.length) {
      /** biome-ignore lint/suspicious/useIterableCallbackReturn: @todo UHF-12501 */
      value.forEach((option) => params.set(key, option.label));
    } else if (value && !Array.isArray(value)) {
      params.set(key, value);
    }
  });

  return params;
};

export const urlAtom = atom((get) => {
  const selections = get(selectionsAtom);
  const params = selectionsToURLParams(selections);
  const currentGlobalParams = new URLSearchParams(window.location.search);
  const newParams = new URLSearchParams({
    ...Object.fromEntries(currentGlobalParams),
    ...Object.fromEntries(params),
  });

  // Make sure keys that are not set are deleted
  ['end', 'page', 'q', 'type', 'start'].forEach((key) => {
    if (!params.has(key)) {
      newParams.delete(key);
    }
  });

  const newUrl = new URL(window.location.toString());
  newUrl.search = newParams.toString();
  window.history.pushState({}, '', newUrl.toString());

  return params.toString();
});
