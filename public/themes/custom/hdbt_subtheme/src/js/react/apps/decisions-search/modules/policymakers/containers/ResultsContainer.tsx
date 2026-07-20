import type { estypes } from '@elastic/elasticsearch';
import { useAtomValue, useSetAtom } from 'jotai';
import { useAtomCallback } from 'jotai/utils';
import { createRef, type SyntheticEvent, useCallback, useEffect, useRef } from 'react';
import useSWR from 'swr';

import { GhostList } from '@/react/common/GhostList';
import useScrollToFirstItem from '@/react/common/hooks/useScrollToFirstItem';
import useScrollToResults from '@/react/common/hooks/useScrollToResults';
import Pagination from '@/react/common/Pagination';
import ResultsEmpty from '@/react/common/ResultsEmpty';
import ResultsError from '@/react/common/ResultsError';
import ResultsHeader from '@/react/common/ResultsHeader';
import type { PolicyMaker } from '../../../common/types/PolicyMaker';
import { ResultCard } from '../components/ResultCard';

import { usePolicymakersQuery } from '../hooks/usePolicymakersQuery';
import { aggsAtom, getElasticUrlAtom, getPageAtom, initializedAtom, searchActiveAtom, setPageAtom } from '../store';

const SIZE = 10;

export const ResultsContainer = () => {
  const aggs = useAtomValue(aggsAtom);
  const currentPage = useAtomValue(getPageAtom);
  const setPage = useSetAtom(setPageAtom);
  const query = usePolicymakersQuery();
  const readInitialized = useAtomCallback(useCallback((get) => get(initializedAtom), []));
  const setInitialized = useSetAtom(initializedAtom);
  const searchActive = useAtomValue(searchActiveAtom);
  const url = useAtomValue(getElasticUrlAtom);
  const scrollTarget = createRef<HTMLHeadingElement>();
  const resultsListRef = useRef<HTMLDivElement>(null);

  const fetcher = useCallback(
    (key: string) =>
      fetch(`${url}/paatokset_policymakers/_search`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: key,
      }).then((res) => res.json()),
    [url],
  );

  // Pass null key to SWR when search not active to prevent fetching
  const { data, error, isLoading, isValidating } = useSWR(searchActive ? query : null, fetcher, {
    revalidateOnFocus: false,
  });

  const loading = isLoading || !aggs;
  const scrollToFirstItem = useScrollToFirstItem(resultsListRef, loading || isValidating);

  useEffect(() => {
    if (!readInitialized() && !loading && !isValidating) {
      setInitialized(true);
    }
  }, [loading, isValidating, readInitialized, setInitialized]);

  useScrollToResults(scrollTarget, readInitialized());

  // Don't render results if search is not active
  if (!searchActive) {
    return null;
  }

  if (!data && loading) {
    return <GhostList count={SIZE} bordered />;
  }

  if (error) {
    return <ResultsError error={error} className='react-search__results' ref={scrollTarget} />;
  }

  if (!data?.hits?.hits.length) {
    return <ResultsEmpty ref={scrollTarget} />;
  }

  const results = data.hits.hits;

  const getTotal = () => {
    if (typeof data?.hits?.total === 'number') {
      return data.hits.total;
    }
    return data?.hits?.total?.value || 0;
  };

  const total = getTotal();
  const pages = Math.floor(total / SIZE);
  const addLastPage = total > SIZE && total % SIZE;

  const getHeaderText = () => {
    if (!total) {
      return '';
    }

    return Drupal.formatPlural(
      total,
      '1 decision maker',
      '@count decision makers',
      {
        '@count': total.toString(),
      },
      { context: 'Policymaker search' },
    );
  };

  const updatePage = (e: SyntheticEvent<HTMLButtonElement>, index: number) => {
    e.preventDefault();
    setPage(index);
    scrollToFirstItem();
  };

  return (
    <div className='react-search__results'>
      <ResultsHeader resultText={getHeaderText()} ref={scrollTarget} />
      <div className='hdbt-search--react__results--container'>
        <div ref={resultsListRef}>
          {results.map((item: estypes.SearchHit<PolicyMaker>) => (
            <ResultCard
              key={item._id}
              field_organization_type={item._source?.field_organization_type}
              field_policymaker_id={item._source?.field_policymaker_id}
              title={item._source?.title}
              trustee_name={item._source?.trustee_name as string[] | undefined}
              trustee_title={item._source?.trustee_title as string[] | undefined}
              url={item._source?.url}
              organization_hierarchy={item._source?.organization_hierarchy}
            />
          ))}
        </div>
        <Pagination
          currentPage={Number(currentPage)}
          pages={5}
          totalPages={addLastPage ? pages + 1 : pages}
          updatePage={updatePage}
        />
      </div>
    </div>
  );
};
