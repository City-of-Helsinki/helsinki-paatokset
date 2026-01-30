import type { estypes } from '@elastic/elasticsearch';
import { useAtomValue, useSetAtom } from 'jotai';
import { useAtomCallback } from 'jotai/utils';
import { useCallback, useEffect } from 'react';
import useSWR from 'swr';

import { ResultsWrapper } from '@/react/common/ResultsWrapper';
import type { PolicyMaker } from '../../../common/types/PolicyMaker';
import { ResultCard } from '../components/ResultCard';
import { usePolicymakersQuery } from '../hooks/usePolicymakersQuery';
import { aggsAtom, getPageAtom, initializedAtom, setPageAtom } from '../store';

export const ResultsContainer = ({ url }: { url: string }) => {
  const aggs = useAtomValue(aggsAtom);
  const currentPage = useAtomValue(getPageAtom);
  const setPage = useSetAtom(setPageAtom);
  const query = usePolicymakersQuery();
  const readInitialized = useAtomCallback(useCallback((get) => get(initializedAtom), []));
  const setInitialized = useSetAtom(initializedAtom);

  const fetcher = useCallback(
    (key: string) =>
      fetch(`${url}/paatokset_policymakers/_search`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: key,
      }).then((res) => res.json()),
    [url],
  );

  const { data, error, isLoading, isValidating } = useSWR(query, fetcher, { revalidateOnFocus: false });

  const loading = isLoading || !aggs;

  useEffect(() => {
    if (!readInitialized() && !loading && !isValidating) {
      setInitialized(true);
    }
  }, [loading, isValidating, readInitialized, setInitialized]);

  const resultItemCallBack = (item: estypes.SearchHit<PolicyMaker>) => {
    return (
      <ResultCard
        key={item._id}
        color_class={item._source?.color_class}
        title={item._source?.title}
        trustee_name={item._source?.trustee_name as string[] | undefined}
        trustee_title={item._source?.trustee_title as string[] | undefined}
        url={item._source?.url}
        organization_hierarchy={item._source?.organization_hierarchy}
      />
    );
  };

  const getTotal = () => {
    if (typeof data?.hits?.total === 'number') {
      return data.hits.total;
    }
    return data?.hits?.total?.value || 0;
  };

  const total = getTotal();

  const getHeaderText = () => {
    if (!total) {
      return '';
    }

    return Drupal.formatPlural(
      total,
      '1 decision-maker',
      '@count decision-makers',
      {},
      { context: 'Policymakers search' },
    );
  };

  return (
    <ResultsWrapper
      {...{ currentPage, data, error, getHeaderText, resultItemCallBack, setPage }}
      isLoading={loading}
      shouldScroll={readInitialized()}
      size={10}
    />
  );
};
