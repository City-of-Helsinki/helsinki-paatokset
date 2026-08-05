// biome-ignore-all lint/complexity/noUselessFragments: @todo UHF-12501
import { useAtomValue, useSetAtom } from 'jotai';
import type { SyntheticEvent } from 'react';
import { GhostList } from '@/react/common/GhostList';
import useSearchFocusManagement from '@/react/common/hooks/useSearchFocusManagement';
import Pagination from '@/react/common/Pagination';
import ResultsEmpty from '@/react/common/ResultsEmpty';
import ResultsError from '@/react/common/ResultsError';
import ResultsHeader from '@/react/common/ResultsHeader';
import type Result from '@/types/Result';
import { ResultCard } from '../components/ResultCard';
import { getPageAtom, setSelectionsAtom } from '../store';
import type { Decision } from '../types/Decision';
import type { Selections } from '../types/Selections';

export const ResultsContainer = ({
  data,
  error,
  isValidating,
  queryString,
  trigger,
}: {
  // biome-ignore lint/suspicious/noExplicitAny: @todo UHF-12501
  data: any;
  // biome-ignore lint/suspicious/noExplicitAny: @todo UHF-12501
  error: any;
  isValidating: boolean;
  queryString: string;
  trigger: Selections;
}) => {
  const setSelections = useSetAtom(setSelectionsAtom);
  const currentPage = useAtomValue(getPageAtom);
  const size = 10;

  const { scrollTarget, loadingHeaderRef, resultsListRef, onPageChange, isSearching } = useSearchFocusManagement(
    isValidating,
    queryString,
    data,
    error,
    trigger,
  );

  const updatePage = (event: SyntheticEvent<HTMLButtonElement>, index: number) => {
    event.preventDefault();
    setSelections({ page: index.toString() }, true);
    onPageChange();
  };

  if (isSearching) {
    return (
      <div key='ghost' className='react-search__results'>
        <ResultsHeader
          resultText={Drupal.t('Searching for results...', {}, { context: 'React search: Fetching results title' })}
          ref={loadingHeaderRef}
        />
        <GhostList count={size} bordered />
      </div>
    );
  }

  if (error) {
    return <ResultsError error={error} className='react-search__results' ref={scrollTarget} />;
  }

  if (!data?.hits?.hits.length) {
    return <ResultsEmpty ref={scrollTarget} />;
  }

  const results = data.hits.hits;
  const total = data.hits.total.value;
  const pages = Math.floor(total / size);
  const addLastPage = total > size && total % size;

  return (
    <div key='results' className='react-search__results'>
      <ResultsHeader
        resultText={
          <>
            {Drupal.formatPlural(
              total,
              '1 decision',
              '@count decisions',
              { '@count': total },
              { context: 'Allu decision search' },
            )}
          </>
        }
        ref={scrollTarget}
      />
      <div className='hdbt-search--react__results--container'>
        <div ref={resultsListRef}>
          {results.map(({ _source }: Result<Decision>) => (
            <ResultCard key={_source.search_api_id[0]} {..._source} />
          ))}
        </div>
        <Pagination
          currentPage={Number(currentPage) || 1}
          pages={5}
          totalPages={addLastPage ? pages + 1 : pages}
          updatePage={updatePage}
        />
      </div>
    </div>
  );
};
