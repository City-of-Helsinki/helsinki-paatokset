import type { estypes } from '@elastic/elasticsearch';

export const getBaseSearchTermQuery = (searchTerm: string, dataFields: string[]): estypes.QueryDslQueryContainer[] => [
  { multi_match: { fields: dataFields, fuzziness: 1, operator: 'or', query: searchTerm, type: 'best_fields' } },
  { multi_match: { boost: 2, fields: dataFields, operator: 'or', query: searchTerm, type: 'phrase' } },
];

export const getAdvancedBoostQuery = (searchTerm: string, dataField: string): estypes.QueryDslQueryContainer[] => [
  { match: { [dataField]: { boost: 3, operator: 'and', query: searchTerm } } },
];
