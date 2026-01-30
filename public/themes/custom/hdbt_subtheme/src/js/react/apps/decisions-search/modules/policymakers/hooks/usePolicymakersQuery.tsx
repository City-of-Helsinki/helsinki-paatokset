import type { estypes } from '@elastic/elasticsearch';
import { useAtomValue } from 'jotai';
import { useMemo } from 'react';
import { PolicymakerIndex } from '../../decisions/enum/IndexFields';
import { Components } from '../enum/Components';
import { submittedStateAtom } from '../store';

export const usePolicymakersQuery = (): string => {
  const submittedState = useAtomValue(submittedStateAtom);

  return useMemo(() => {
    const { currentLanguage } = drupalSettings.path;

    const filter: estypes.QueryDslQueryContainer[] = [
      { term: { [PolicymakerIndex.FIELD_POLICYMAKER_EXISTING]: true } },
      {
        bool: {
          should: [
            { term: { [PolicymakerIndex.SEARCH_API_LANGUAGE]: currentLanguage } },
            { term: { [PolicymakerIndex.HAS_TRANSLATION]: false } },
          ],
        },
      },
    ];

    const should: estypes.QueryDslQueryContainer[] = [];

    const searchTerm = submittedState?.[Components.SEARCHBAR]?.trim();
    if (searchTerm?.length) {
      should.push({ wildcard: { [`${PolicymakerIndex.TITLE}.keyword`]: `*${searchTerm}*` } });
    }

    const sectorFilter = submittedState?.[Components.SECTOR];
    if (sectorFilter?.length) {
      filter.push({
        terms: { [PolicymakerIndex.FIELD_SECTOR_NAME]: sectorFilter.map((sector) => sector.value) },
      });
    }

    const query: estypes.QueryDslQueryContainer = { bool: { filter } };

    if (should.length) {
      query.bool!.should = should;
    }

    const size = 10;
    const page = submittedState?.[Components.PAGE] || 1;

    const result = {
      query,
      from: size * (page - 1),
      size,
      sort: [{ [PolicymakerIndex.TITLE]: 'asc' }],
    };

    return JSON.stringify(result);
  }, [submittedState]);
};
