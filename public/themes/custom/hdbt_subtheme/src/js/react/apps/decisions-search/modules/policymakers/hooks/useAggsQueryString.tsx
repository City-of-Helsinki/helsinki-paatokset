import { PolicymakerIndex } from '../../decisions/enum/IndexFields';
import { Components } from '../enum/Components';

export const useAggsQueryString = () => {
  const query = {
    query: {
      bool: {
        filter: [{ term: { [PolicymakerIndex.FIELD_POLICYMAKER_EXISTING]: true } }],
      },
    },
    aggs: {
      [Components.SECTOR]: {
        terms: {
          field: PolicymakerIndex.FIELD_SECTOR_NAME,
          size: 100,
          order: { _key: 'asc' },
        },
      },
    },
    size: 0,
  };

  return JSON.stringify(query);
};
