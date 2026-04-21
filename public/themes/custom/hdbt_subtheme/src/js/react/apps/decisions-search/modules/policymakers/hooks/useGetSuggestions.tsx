import type { estypes } from '@elastic/elasticsearch';
import type { PolicyMaker } from '../../../common/types/PolicyMaker';
import { PolicymakerIndex } from '../../decisions/enum/IndexFields';

const emptyResult = { options: [] };

export const fetchSuggestions = async (searchTerm: string | undefined, url: string) => {
  if (!searchTerm?.length) {
    return emptyResult;
  }

  const normalizedSearchTerm = searchTerm.toLowerCase().replace(/\s+/g, '*');

  const query = {
    query: {
      bool: {
        filter: [{ term: { [PolicymakerIndex.FIELD_POLICYMAKER_EXISTING]: true } }],
        should: [
          { wildcard: { [`${PolicymakerIndex.TITLE}`]: `*${normalizedSearchTerm}*` } },
          { wildcard: { [`${PolicymakerIndex.DECISIONMAKER_COMBINED_TITLE}`]: `*${normalizedSearchTerm}*` } },
          { wildcard: { [`${PolicymakerIndex.TRUSTEE_NAME}`]: `*${normalizedSearchTerm}*` } },
          { wildcard: { [`${PolicymakerIndex.FIELD_FIRST_NAME}`]: `*${normalizedSearchTerm}*` } },
          { wildcard: { [`${PolicymakerIndex.FIELD_LAST_NAME}`]: `*${normalizedSearchTerm}*` } },
        ],
        minimum_should_match: 1,
      },
    },
    collapse: { field: `${PolicymakerIndex.TITLE}.keyword` },
    _source: false,
    fields: [PolicymakerIndex.TITLE, PolicymakerIndex.DECISIONMAKER_COMBINED_TITLE],
    size: 5,
    from: 0,
  };

  const response = await fetch(`${url}/paatokset_policymakers/_search`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(query),
  });

  const json = await response.json();

  if (json.hits?.hits) {
    const options = json.hits.hits.map((hit: estypes.SearchHit<PolicyMaker>) => {
      const title = hit.fields?.decisionmaker_combined_title?.[0] || hit.fields?.title?.[0] || '';
      return { value: title, label: title };
    });
    return { options };
  }

  return emptyResult;
};
