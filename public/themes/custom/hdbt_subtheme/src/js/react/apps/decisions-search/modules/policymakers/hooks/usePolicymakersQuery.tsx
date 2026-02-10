import type { estypes } from '@elastic/elasticsearch';
import { useAtomValue } from 'jotai';
import { useMemo } from 'react';
import { PolicymakerIndex } from '../../decisions/enum/IndexFields';
import { Components } from '../enum/Components';
import { submittedStateAtom } from '../store';
import { getBaseSearchTermQuery } from '../../../common/utils/Query';

export const usePolicymakersQuery = (): string => {
  const submittedState = useAtomValue(submittedStateAtom);

  return useMemo(() => {
    const { currentLanguage } = drupalSettings.path;
    const filter: estypes.QueryDslQueryContainer[] = [
      { term: { [PolicymakerIndex.FIELD_POLICYMAKER_EXISTING]: true } },
      { term: { [PolicymakerIndex.SEARCH_API_LANGUAGE]: currentLanguage } },
    ];

    const should: estypes.QueryDslQueryContainer[] = [];

    const searchTerm = submittedState?.[Components.SEARCHBAR]?.toString().trim();
    if (searchTerm?.length) {
      getBaseSearchTermQuery(searchTerm, [
        `${PolicymakerIndex.TITLE}^5`,
        `${PolicymakerIndex.DECISIONMAKER_COMBINED_TITLE}^2`,
      ]).forEach((item) => {
        should.push(item);
      });
      should.push({ wildcard: { [`${PolicymakerIndex.TITLE}.keyword`]: `*${searchTerm}*` } });
    }

    const sectorFilter = submittedState?.[Components.SECTOR];
    if (sectorFilter?.length) {
      filter.push({
        terms: { [PolicymakerIndex.FIELD_SECTOR_NAME]: sectorFilter.map((sector) => sector.value) },
      });
    }

    const query: estypes.QueryDslQueryContainer = { bool: { filter } };

    if (should.length && query.bool) {
      query.bool.should = should;
      query.bool.minimum_should_match = 1;
    }

    const size = 10;
    const page = submittedState?.[Components.PAGE] || 1;

    const alphabeticalSort = { [`${PolicymakerIndex.TITLE}.keyword`]: 'asc' };
    const sort = searchTerm ? [{ _score: 'desc', ...alphabeticalSort }] : [alphabeticalSort];

    const result = {
      query,
      from: size * (page - 1),
      size,
      sort,
    };

    return JSON.stringify(result);
  }, [submittedState]);
};
