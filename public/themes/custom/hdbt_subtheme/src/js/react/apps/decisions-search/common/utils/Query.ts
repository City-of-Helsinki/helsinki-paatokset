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
      type: 'best_fields',
      operator: 'or',
      fuzziness: 'AUTO'
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

export const getAdvancedBoostQuery = (searchTerm: string, dataField: string) => [
  {
    match: {
      [dataField]: {
        query: searchTerm,
        operator: 'and',
        boost: 10,
      }
    }
  }
];
