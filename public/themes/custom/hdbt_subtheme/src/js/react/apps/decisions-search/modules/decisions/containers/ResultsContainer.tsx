import type { estypes } from '@elastic/elasticsearch';
import { useAtomValue, useSetAtom } from 'jotai';
import { useAtomCallback } from 'jotai/utils';
import { useCallback, useEffect } from 'react';
import useSWR from 'swr';

import { ResultsWrapper } from '@/react/common/ResultsWrapper';
import type { Decision } from '../../../common/types/Decision';
import { ResultCard } from '../components/ResultCard';
import { ResultsSort } from '../components/ResultsSort';
import { useDecisionsQuery } from '../hooks/useDecisionsQuery';
import { aggsAtom, getPageAtom, initializedAtom, setPageAtom } from '../store';

export const ResultsContainer = ({ url }: { url: string }) => {
  const aggs = useAtomValue(aggsAtom);
  const currentPage = useAtomValue(getPageAtom);
  const setPage = useSetAtom(setPageAtom);
  const query = useDecisionsQuery();
  const readInitialized = useAtomCallback(
    useCallback((get) => get(initializedAtom), []),
  );
  const setInitialized = useSetAtom(initializedAtom);

  const fetcher = useCallback(
    (key: string) =>
      fetch(`${url}/paatokset_decisions/_search`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: key,
      }).then((res) => res.json()),
    [url],
  );

  const { data, error, isLoading, isValidating } = useSWR(query, fetcher, {
    revalidateOnFocus: false,
  });

  const loading = isLoading || !aggs;

  useEffect(() => {
    if (!readInitialized() && !loading && !isValidating) {
      setInitialized(true);
    }
  }, [loading, isValidating, readInitialized, setInitialized]);

  const resultItemCallBack = (item: estypes.SearchHit<Decision>) => {
    if (!item.inner_hits?.preferred_version?.hits.hits[0]) {
      console.error(
        'No preferred version found for decision with unique_issue_id:',
        item._id,
      );
      return null;
    }

    const preferred_version = item.inner_hits.preferred_version.hits.hits[0];

    return (
      <ResultCard
        key={preferred_version.fields.id.toString()}
        {...item.inner_hits.preferred_version.hits.hits[0].fields}
      />
    );
  };

  const getCustomTotal = () => {
    if (!data?.aggregations?.total_issues?.value) {
      return 0;
    }
    return data.aggregations.total_issues.value > 9999
      ? 10000
      : data.aggregations.total_issues.value;
  };
  const customTotal = getCustomTotal();

  const getHeaderText = () => {
    if (!customTotal) {
      return '';
    }

    return customTotal > 9999
      ? Drupal.t('Over 10 000 decisions', {}, { context: 'Decisions search' })
      : Drupal.formatPlural(
          customTotal,
          '1 decision',
          '@count decisions',
          {},
          { context: 'Decisions search' },
        );
  };
  const sortElement = customTotal && <ResultsSort />;

  return (
    <ResultsWrapper
      {...{
        currentPage,
        customTotal,
        data,
        error,
        getHeaderText,
        resultItemCallBack,
        setPage,
        sortElement,
      }}
      isLoading={loading}
      shouldScroll={readInitialized()}
    />
  );
};
