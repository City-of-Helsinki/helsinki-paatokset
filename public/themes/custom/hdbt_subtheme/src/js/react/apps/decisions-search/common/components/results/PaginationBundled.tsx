import { Fragment } from 'react';
import { IconAngleLeft, IconAngleRight } from 'hds-react';
import {Pagination as HDBTPagination} from '@/react/common/Pagination';
import classNames from 'classnames';

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
          {...{
            pages,
            currentPage
          }}
          totalPages={realTotalPages}
          updatePage={setPage}
        />
        {process.env.REACT_APP_DEVELOPER_MODE &&
          <div style={{color: 'red'}}>Original amount of pages: {totalPages}</div>
        }
      </>
  ) : null;
};

export default Pagination;
