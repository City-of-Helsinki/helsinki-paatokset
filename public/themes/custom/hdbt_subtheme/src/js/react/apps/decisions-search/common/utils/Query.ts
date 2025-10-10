export const getBaseSearchTermQuery = (searchTerm: string, dataFields: string[]) => [
  {
    multi_match: {
      query: searchTerm,
      fields: dataFields,
      type: 'best_fields',
      operator: 'or',
      fuzziness: 0
    }
  },
  {
    multi_match: {
      query: searchTerm,
      fields: dataFields,
      type: 'phrase',
      operator: 'or'
    }
  },
  {
    multi_match: {
      query: searchTerm,
      fields: dataFields,
      type: 'phrase_prefix',
      operator: 'or'
    }
  },
];
