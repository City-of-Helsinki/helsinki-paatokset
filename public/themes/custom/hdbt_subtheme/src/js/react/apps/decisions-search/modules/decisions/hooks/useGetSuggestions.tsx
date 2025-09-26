import { type estypes } from '@elastic/elasticsearch';

import { useDecisionsQuery } from './useDecisionsQuery';
import { Decision } from '../../../common/types/Decision';
import { DecisionIndex } from '../enum/IndexFields';

export const useGetSuggestions = async(searchTerm: string, url: string) => {
  const baseQuery = useDecisionsQuery(true);

  if (!searchTerm || !searchTerm.length || searchTerm.length < 2) {
    return [];
  }

  const {aggs, collapse, size, from, ...rest} = baseQuery;
  const suggestionQuery = {...rest};

  suggestionQuery.collapse = {
    field: `${[DecisionIndex.SUBJECT]}.keyword`,
  };
  suggestionQuery.fields = [DecisionIndex.SUBJECT];

  const response = await fetch(`${url}/paatokset_decisions/_search`, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
    },
    body: JSON.stringify({
      ...suggestionQuery,
      size: 5,
      from: 0,
    }),
  });

  const json = await response.json();

  if (json.hits && json.hits.hits) {
    return json.hits.hits.map((hit: estypes.SearchHit<Decision>) => ({value: hit.fields.subject.toString()}));
  }

  return [];
};
