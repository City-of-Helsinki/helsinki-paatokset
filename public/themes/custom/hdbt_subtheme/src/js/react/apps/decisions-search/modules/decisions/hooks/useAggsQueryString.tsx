import { useAtomValue } from 'jotai';

import { Components } from '../enum/Components';
import { DecisionIndex, PolicymakerIndex } from '../enum/IndexFields';
import { initialParamsAtom } from '../store';

export const useAggsQueryString = () => {
  const initialParams = useAtomValue(initialParamsAtom);

  const categoryAggs = {
    [Components.CATEGORY]: {
      terms: { field: DecisionIndex.TOP_CATEGORY_CODE, size: 100 },
    },
  };

  const categoryQuery = {
    aggs: categoryAggs,
    query: { match_all: {} },
    size: 0,
  };

  if (!initialParams.get(Components.DECISIONMAKER)?.length) {
    return JSON.stringify(categoryQuery);
  }

  const decisionMakerAggs = {
    [Components.DECISIONMAKER]: {
      terms: { field: DecisionIndex.POLICYMAKER_ID, size: 1000 },
      aggs: {
        [PolicymakerIndex.TITLE]: {
          terms: {
            field: `${PolicymakerIndex.DECISIONMAKER_COMBINED_TITLE}.keyword`,
            size: 1000,
          },
        },
      },
    },
  };

  const msearchQuery = [
    {},
    categoryQuery,
    {},
    {
      aggs: decisionMakerAggs,
      query: {
        bool: {
          filter: [
            {
              terms: {
                [DecisionIndex.POLICYMAKER_ID]: initialParams
                  .get(Components.DECISIONMAKER)
                  .split(','),
              },
            },
            { term: { _index: 'paatokset_policymakers' } },
            {
              term: {
                [PolicymakerIndex.SEARCH_API_LANGUAGE]:
                  drupalSettings.path.currentLanguage,
              },
            },
          ],
        },
      },
      size: 0,
    },
  ]
    .map((query) => JSON.stringify(query))
    .join('\n');

  return `${msearchQuery}\n`;
};
