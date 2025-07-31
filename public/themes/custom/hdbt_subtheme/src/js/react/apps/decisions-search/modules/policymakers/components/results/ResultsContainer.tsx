import { useRef } from 'react';
import { ReactiveList } from '@appbaseio/reactivesearch';
import { useTranslation } from 'react-i18next';
import classNames from 'classnames';

import SearchComponents from '../../enum/SearchComponents';
import IndexFields from '../../enum/IndexFields';
import ResultCard from './ResultCard';
import Pagination from '../../../../common/components/results/Pagination';
import SearchLoader from '../../../../common/components/results/SearchLoader';

type Props = {
  getLastRefreshed: Function,
}

const ResultsContainer = ({getLastRefreshed}: Props) => {
  const { t } = useTranslation();
  const resultsContainer = useRef<HTMLDivElement|null>(null);

  const scrollToResults = () => {
    if(resultsContainer.current) {
      resultsContainer.current.scrollIntoView();
    }
  }

  return (
    <div
      ref={resultsContainer}
      className={classNames(
        'decisions-search-results',
        'policymaker-search-results',
    )}>
      <ReactiveList
        className={classNames(
          'decisions-search-results__container',
          'policymaker-search-results__container',
          'container'
        )}
        componentId={SearchComponents.RESULTS}
        size={10}
        pages={3}
        loader={<SearchLoader />}
        pagination={true}
        dataField={IndexFields.TITLE}
        onPageChange={scrollToResults}
        URLParams={true}
        defaultQuery={() => (
          {
            query: {
              "bool": {
                "must": [
                  {
                    "match": {
                      [IndexFields.POLICYMAKER_EXISTING]: true
                    }
                  },
                  {
                    "bool": {
                      "should": [
                        {
                          "match": {[IndexFields.LANGUAGE]: t('SEARCH:langcode')}
                        },
                        {
                          "match": {[IndexFields.HAS_TRANSLATION]: false}
                        }
                      ]
                    },
                  }
                ],
                "must_not": {
                  "term": {
                    "force_refresh": getLastRefreshed()
                  }
                }
              }
            }
          }          
        )}
        react={{
          or: [
            SearchComponents.SEARCH_BAR,
            SearchComponents.WILDCARD
          ],
          and: [
            SearchComponents.SECTOR
          ]
        }}
        renderResultStats={(stats) => (
          <div className='decisions-search-results__stats'>
            <span className={classNames(
              'decisions-search-results__stats-count',
              'policymaker-search-results__stats-count'
            )}>
              <strong>{stats.numberOfResults}</strong>
              {t('SEARCH:results-count')}
            </span>
          </div>
        )}
        renderPagination={({ pages, totalPages, currentPage, setPage, setSize }) => (
          <Pagination
            pages={pages}
            totalPages={totalPages}
            currentPage={currentPage}
            setPage={setPage}
            setSize={setSize}
          />
        )}
        renderNoResults={() => (
          <div className='decisions-search-results__stats'>
            <span className='decisions-search-results__stats-count'>
              <strong>0</strong>
              {t('SEARCH:results-count')}
            </span>
          </div>
        )}
        render={({ data }) => (
          <ReactiveList.ResultCardsWrapper
            style={{
              margin: 0,
              gap: '24px',
              width: '100%'
            }}
          >
            {data.map((item: any) => (
              <ResultCard
                {...item}
                key={item._id}
              />
            ))}
          </ReactiveList.ResultCardsWrapper>
        )}
      />
    </div>
  );
};

export default ResultsContainer;
