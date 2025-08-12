import { Pagination as HDBTPagination } from '@/react/common/Pagination';

type Props = {
  pages: number,
  totalPages: number,
  currentPage: number,
  setPage: Function,
  setSize: Function
};

const Pagination = ({
  pages,
  totalPages,
  currentPage,
  setPage,
  setSize
}: Props) => (
  <HDBTPagination
    {...{
      pages,
      totalPages,
    }}
    currentPage={currentPage + 1}
    updatePage={(e, i) => {
      e.preventDefault();
      setPage(i - 1);
    }}
  />
);

export default Pagination;
