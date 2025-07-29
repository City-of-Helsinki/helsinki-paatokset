import { Fragment } from 'react';
import { IconAngleLeft, IconAngleRight } from 'hds-react';
import classNames from 'classnames';

import styles from './Pagination.module.scss';

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
}

export const getPagination = (current: number, pages: number, totalPages: number, searchState: object) => {
  const pagesPerSide = (pages - 1) / 2;
  let pagesLeft = pagesPerSide * 2;
  let prevPages: Array<number> = [];
  let nextPages: Array<number> = [];

  if(pagesPerSide > 0) {
    for(let i = current - 1; prevPages.length < pagesPerSide && i >= 0; i--) {
      prevPages.push(i);
      pagesLeft--;
    }

    for(let i = current + 1; (pagesLeft > 0 && i < totalPages); i++) {
      nextPages.push(i);
      pagesLeft--;
    }
  }

  prevPages.reverse();

  return {
    prevPages,
    nextPages
  };
}

const Pagination = ({
  pages,
  totalPages,
  currentPage,
  setPage,
  size,
  searchState
}: Props) => {
  const realTotalPages = getRealTotalPages(searchState, totalPages, size);
  const { prevPages, nextPages } = getPagination(currentPage, pages, realTotalPages, searchState)
  const prevPageExists = currentPage - 1 >= 0;
  const nextPageExists = currentPage + 1 < realTotalPages;
  const firstWithinRange = prevPages.includes(0) || !prevPages.length;
  const lastWithinRange = nextPages.includes(realTotalPages - 1) || !nextPages.length;
  const selectPage = Number.isFinite(realTotalPages) ? (
    <div className={styles.Pagination}>
      <button
        onClick={() => {
          if(prevPageExists) {
            setPage(currentPage - 1)
          }
        }}
        disabled={!prevPageExists}
        className={styles.Pagination__control}
      >
        <IconAngleLeft />
      </button>
      {!firstWithinRange &&
        <Fragment>
          <button
            onClick={() => {
              if(prevPageExists) {
                setPage(0)
              }
            }}
            className={styles.Pagination__item}
          >
            1
          </button>
          {prevPages[0] - 1 > 0 &&
            <span className={classNames(
              styles.Pagination__item,
              styles['Pagination__item--separator']
            )}>...</span>
          }
        </Fragment>
      }
      {prevPages.map(pageIndex => (
        <button
          className={styles.Pagination__item}
          onClick={() => setPage(pageIndex)}
          key={pageIndex}
        >
          { pageIndex + 1 }
        </button>
      ))}
      <button className={classNames(
        styles.Pagination__item,
        styles['Pagination__item--current']
      )}
      >
        {currentPage + 1}
      </button>
      {nextPages.map(pageIndex => (
        <button
          className={styles.Pagination__item}
          onClick={() => setPage(pageIndex)}
          key={pageIndex}
        >
          { pageIndex + 1 }
        </button>
      ))}
      {!lastWithinRange &&
        <Fragment>
          {nextPages[nextPages.length - 1] + 1 !== realTotalPages &&
            <span className={classNames(
              styles.Pagination__item,
              styles['Pagination__item--separator']
            )}>...</span>
          }
          <button
            onClick={() => (setPage(realTotalPages - 1))}
            className={styles.Pagination__item}
          >
            {realTotalPages}
          </button>
        </Fragment>
      }
      <button
        onClick={() => {
          if(nextPageExists) {
            setPage(currentPage + 1)
          }
        }}
        disabled={!nextPageExists}
        className={styles.Pagination__control}
      >
        <IconAngleRight />
      </button>
      {process.env.REACT_APP_DEVELOPER_MODE &&
        <div style={{color: 'red'}}>Original amount of pages: {totalPages}</div>
      }
    </div>
  ) : null;
  return selectPage;
}

export default Pagination;
