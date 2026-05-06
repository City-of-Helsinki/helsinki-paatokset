import type { estypes } from '@elastic/elasticsearch';
import type { Decision } from '../../../common/types/Decision';
import { DecisionIndex } from '../enum/IndexFields';

const emptyResult = { options: [] };

export const fetchSuggestions = async (searchTerm: string, url: string) => {
  if (!searchTerm?.length) {
    return emptyResult;
  }

  const query = {
    query: {
      bool: {
        filter: [{ exists: { field: DecisionIndex.MEETING_DATE } }],
        should: [
          { match_phrase_prefix: { [DecisionIndex.SUBJECT]: searchTerm } },
          { match: { [DecisionIndex.SUBJECT]: { query: searchTerm, boost: 2 } } },
        ],
        minimum_should_match: 1,
      },
    },
    collapse: { field: `${DecisionIndex.SUBJECT}.keyword` },
    _source: false,
    fields: [DecisionIndex.SUBJECT],
    size: 5,
    from: 0,
  };

  const response = await fetch(`${url}/paatokset_decisions/_search`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(query),
  });

  const json = await response.json();

  if (json.hits?.hits) {
    const options = json.hits.hits.map((hit: estypes.SearchHit<Decision>) => {
      const subject = (hit.fields?.[DecisionIndex.SUBJECT] as string[] | undefined)?.[0] ?? '';
      return { value: subject, label: subject };
    });
    return { options };
  }

  return emptyResult;
};
