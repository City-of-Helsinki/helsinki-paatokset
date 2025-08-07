import { Fragment } from 'react';
import { IconAngleLeft, IconAngleRight } from 'hds-react';
import classNames from 'classnames';
import { Pagination as HDBTPagination } from '@/react/common/Pagination';

type Props = {
  pages: number,
  totalPages: number,
  currentPage: number,
  setPage: Function,
  size: number,
  searchState: object,
};

export const getRealTotalPages = (searchState: any, totalPages: number, size: number) => {
  if (!searchState.results) {
    return totalPages;
  }
  if (searchState.results.aggregations && searchState.results.aggregations.unique_issue_id && searchState.results.aggregations.unique_issue_id.buckets.length > 0) {
    // Special case when max amount of hits is reached.
    if (searchState.results.aggregations.unique_issue_id.buckets.length >= 10000) {
      return totalPages - 1;
    }
    return Math.ceil(searchState.results.aggregations.unique_issue_id.buckets.length / size);
  }
  return totalPages;
};

const Pagination = ({
  pages,
  totalPages,
  currentPage,
  setPage,
  size,
  searchState
}: Props) => {
  const realTotalPages = getRealTotalPages(searchState, totalPages, size);
  return Number.isFinite(realTotalPages) ? (
      <>
        <HDBTPagination
          pages={pages}
          currentPage={currentPage + 1}
          totalPages={realTotalPages}
          updatePage={(e, i) => {
            e.preventDefault();
            setPage(i - 1);
          }}
        />
        {_DEBUG_MODE_ &&
          <div style={{ color: 'red' }}>Original amount of pages: {totalPages}</div>
        }
      </>
  ) : null;
};

export default Pagination;
