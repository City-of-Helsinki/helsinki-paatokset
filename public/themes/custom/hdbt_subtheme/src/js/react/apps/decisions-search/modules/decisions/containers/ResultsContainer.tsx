import { type estypes } from '@elastic/elasticsearch';
import { useAtomValue, useSetAtom } from 'jotai';
import useSWR from 'swr';

import { aggsAtom, getPageAtom, setPageAtom } from '../store';
import { ResultCard } from '../components/ResultCard';
import { ResultsWrapper } from '@/react/common/ResultsWrapper';
import { type Decision } from '../../../common/types/Decision';
import { useDecisionsQuery } from '../hooks/useDecisionsQuery';
import { ResultsSort } from '../components/ResultsSort';

export const ResultsContainer = ({
  url,
}: {
  url: string;
}) => {
  const aggs = useAtomValue(aggsAtom);
  const currentPage = useAtomValue(getPageAtom);
  const setPage = useSetAtom(setPageAtom);
  const query = useDecisionsQuery();

  const fetcher = () => fetch(`${url}/paatokset_decisions/_search`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
      },
      body: query,
    }).then((res) => res.json());

  const { data, error, isLoading } = useSWR(query, fetcher, {
    revalidateOnFocus: false
  });

  const resultItemCallBack = (item: estypes.SearchHit<Decision>) => {
    if (!item.inner_hits?.preferred_version?.hits.hits[0]) {
      console.error('No preferred version found for decision with unique_issue_id:', item._id);
      return null;
    }

    const preferred_version = item.inner_hits.preferred_version.hits.hits[0];

    return <ResultCard
      key={preferred_version.fields.id.toString()}
      has_multiple_decisions={item.inner_hits.preferred_version.hits.total.value > 1}
      {...item.inner_hits.preferred_version.hits.hits[0].fields}
    />;
  };

  const customTotal = data?.aggregations?.total_issues.value;
  const getHeaderText = () => data?.hits?.total?.value ? Drupal.formatPlural(customTotal, '1 decision', '@count decisions', { context: 'Decisions search' }) : ''; 
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
      isLoading={isLoading || !aggs}
    />
  ); 
};
 