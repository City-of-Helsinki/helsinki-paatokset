import { Fragment } from 'react';
import { IconAngleLeft, IconAngleRight } from 'hds-react';
import classNames from 'classnames';

import styles from './Pagination.module.scss';

type Props = {
  pages: number,
  totalPages: number,
  currentPage: number,
  setPage: Function,
  setSize: Function
};

export const getPagination = (current: number, pages: number, totalPages: number) => {
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
  setSize
}: Props) => {
  const { prevPages, nextPages } = getPagination(currentPage, pages, totalPages)
  const prevPageExists = currentPage - 1 >= 0;
  const nextPageExists = currentPage + 1 < totalPages;
  const firstWithinRange = prevPages.includes(0) || !prevPages.length;
  const lastWithinRange = nextPages.includes(totalPages - 1) || !nextPages.length;
  const selectPage = Number.isFinite(totalPages) ? (
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
          {nextPages[nextPages.length - 1] + 1 !== totalPages &&
            <span className={classNames(
              styles.Pagination__item,
              styles['Pagination__item--separator']
            )}>...</span>
          }
          <button
            onClick={() => (setPage(totalPages - 1))}
            className={styles.Pagination__item}
          >
            {totalPages}
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
    </div>
  ) : null;
  return selectPage;
}

export default Pagination;
