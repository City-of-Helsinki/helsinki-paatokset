import { createRef, SyntheticEvent } from 'react';
import { useAtomValue, useSetAtom } from 'jotai';

import Result from '@/types/Result';
import { Decision } from '../types/Decision';
import { ResultCard } from '../components/ResultCard';
import Pagination from '@/react/common/Pagination';
import ResultsHeader from '@/react/common/ResultsHeader';
import { getPageAtom, setSelectionsAtom } from '../store';
import { GhostList } from '@/react/common/GhostList';
import useScrollToResults from '@/react/common/hooks/useScrollToResults';
import ResultsError from '@/react/common/ResultsError';
import ResultsEmpty from '@/react/common/ResultsEmpty';

export const ResultsContainer = ({
  data,
  error,
  isLoading,
}: {
  data: any,
  error: any,
  isLoading: boolean,
}) => {
  const setSelections = useSetAtom(setSelectionsAtom);
  const scrollTarget = createRef<HTMLHeadingElement>();
  const currentPage = useAtomValue(getPageAtom);
  const size = 10;

  useScrollToResults(scrollTarget, true);

  if (!data && isLoading) {
    return <GhostList
      count={size}
      bordered
    />;
  }

  if (error) {
    return (
      <ResultsError
        error={error}
        className='react-search__results'
        ref={scrollTarget}
      />
    );
  }

  if (!data?.hits?.hits.length) {
    return <ResultsEmpty ref={scrollTarget} />;
  }

  const results = data.hits.hits;
  const total = data.hits.total.value;
  const pages = Math.floor(total / size);
  const addLastPage = total > size && total % size;

  return (
    <div className='react-search__results'>
      <ResultsHeader
        resultText={
          <>
            {Drupal.formatPlural(total, '1 decision', '@count decisions', {
              '@count': total
            }, {
              context: 'Allu decision search',
            })}
          </>
        }
        ref={scrollTarget}
      />
      <div className='hdbt-search--react__results--container'>
        {results.map(({ _source }: Result<Decision>) => (
          <ResultCard key={_source.search_api_id[0]} {..._source} />
        ))}
        <Pagination
          currentPage={Number(currentPage) || 1}
          pages={5}
          totalPages={addLastPage ? pages + 1 : pages}
          updatePage={(event: SyntheticEvent<HTMLButtonElement>, index: number) => {
            event.preventDefault();
            setSelections({
              page: index.toString()
            }, true);
          }}
        />
      </div>
    </div>
  );
};
