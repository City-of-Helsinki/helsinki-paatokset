import React, { useContext, useRef, useState } from 'react';
import { ReactiveList, StateProvider } from '@appbaseio/reactivesearch';
import { useTranslation } from 'react-i18next';
import { isOperatorSearch } from '../../../../utils/OperatorSearch';

import ResultCard from './ResultCard';
import SortSelect from './SortSelect';
import SizeSelect from './SizeSelect';
import useWindowDimensions from '../../../../hooks/useWindowDimensions';
import SearchComponents from '../../enum/SearchComponents';
import IndexFields from '../../enum/IndexFields';
import { Sort } from '../../enum/Sort';
import Pagination from '../../../../common/components/results/PaginationBundled';
import PhantomCard from './PhantomCard';
import SearchLoader from '../../../../common/components/results/SearchLoader';
import { Notification } from 'hds-react';
import { OperatorGuideContext } from '../../../../index';

import resultsStyles from '../../../../common/styles/Results.module.scss';
import styles from './ResultsContainer.module.scss';
import classNames from 'classnames';

const ResultsContainer = () => {
  const [sort, setSort] = useState<string|undefined>(Sort.SCORE);
  const [size, setSize] = useState<number>(12);
  const { t } = useTranslation();
  const { width } = useWindowDimensions();
  const resultsContainer = useRef<HTMLDivElement|null>(null);
  const operatorGuideUrl = useContext(OperatorGuideContext);
  
  const pages = width < 768 ? 3 : 5;
  let pageLoads = 0;
  const scrollToResults = () => {
    // Don't scroll to results on first page load.
    if(resultsContainer.current && pageLoads > 1) {
      resultsContainer.current.scrollIntoView();
    }
    pageLoads++;
  }

  const cardWrapperStyles: any = {
    margin: 0,
    gap: '24px',
    width: '100%'
  };
  if(width > 1281) {
    cardWrapperStyles.justifyContent = 'space-between'
  }

  const getRealResultsAmount = (searchState:any) => {
    if (!searchState.results) {
      return 0;
    }
    if (searchState.results.aggregations && searchState.results.aggregations.unique_issue_id && searchState.results.aggregations.unique_issue_id.buckets.length > 0) {
      return searchState.results.aggregations.unique_issue_id.buckets.length;
    }
    return searchState.results.hits?.total || 0;
  }

  const dataField = sort === Sort.SCORE ? IndexFields.SCORE : IndexFields.MEETING_DATE;
  const sortBy = (sort === Sort.SCORE || sort === Sort.DATE_DESC) ? 'desc' : 'asc';
  const customQuery = () => {
    return {
      query: {
        function_score: {
          boost: 10,
          query: {
            bool: {
              must: [
                {"exists": {"field": IndexFields.MEETING_DATE}},
                {
                  // Query for documents that are translated to current
                  // language, or they are not translated at all.
                  bool: {
                    should: [
                      {"term": {[IndexFields.LANGUAGE]: t('SEARCH:langcode')}},
                      {"term": {[IndexFields.HAS_TRANSLATION]: false}}
                    ]
                  }
                }
              ]
            }
          },
          functions: [
            {gauss:
              {
                [IndexFields.MEETING_DATE]: {
                  scale: '30d'
                }
              }
            }
          ]
        }
      },
      aggs: {
        [IndexFields.UNIQUE_ISSUE_ID]: {
          terms: {
            field: IndexFields.UNIQUE_ISSUE_ID,
            size: 10000,
            show_term_doc_count_error: true
          }
        }
      },
      collapse: {
        field: IndexFields.UNIQUE_ISSUE_ID
      }
    }
  };

  return (
    <div className={resultsStyles.ResultsContainer} ref={resultsContainer}>
      <ReactiveList
        className={classNames(
          resultsStyles.ResultsContainer__container,
          styles.ResultsContainer__container,
          'container'
        )}
        onQueryChange={
          function(prevQuery, nextQuery) {
            const query = customQuery();
            if (typeof nextQuery.aggs === "undefined") {
              nextQuery.aggs = query.aggs;
            }
            if (typeof nextQuery.collapse === "undefined") {
              nextQuery.collapse = query.collapse;
            }
          }
        }
        scrollOnChange={false}
        componentId={SearchComponents.RESULTS}
        size={size}
        pagination={true}
        pages={pages}
        dataField={dataField}
        sortBy={sortBy}
        onPageChange={scrollToResults}
        URLParams={true}
        loader={<SearchLoader />}
        react={{
          or: [
            SearchComponents.SEARCH_BAR,
            SearchComponents.WILDCARD,
          ],
          and: [
            SearchComponents.CATEGORY,
            SearchComponents.MEETING_DATE,
            SearchComponents.DM,
          ]
        }}
        renderResultStats={(stats) => (
          <StateProvider includeKeys={['aggregations', 'hits', 'took']} render={({ searchState }) => (
            <div className={resultsStyles.ResultsContainer__stats}>
              <span className={resultsStyles.stats__count}>
                {t('DECISIONS:results-count')} <strong>{getRealResultsAmount(searchState)}</strong>
              </span>
              <span className={resultsStyles.stats__size}>
                <SizeSelect setSize={setSize} />
                {t('SEARCH:per-page')}
              </span>
              {process.env.REACT_APP_DEVELOPER_MODE &&
                <span>
                  <span style={{color: 'red', paddingLeft: '15px'}}>Total hits: {stats.numberOfResults}</span>
                  <span style={{color: 'red', paddingLeft: '15px'}}>Time: {stats.time} ms</span>
                </span>
              }
            </div>
          )} />
        )}
        renderPagination={({ pages, totalPages, currentPage, setPage, setSize }) => (
          <StateProvider includeKeys={['aggregations', 'hits']} render={({ searchState }) => (
            <Pagination
              pages={pages}
              totalPages={totalPages}
              currentPage={currentPage}
              setPage={setPage}
              size={size}
              searchState={searchState}
            />
          )} />
        )}
        renderNoResults={() => (
          <div className={resultsStyles.ResultsContainer__stats}>
            <span className={resultsStyles.stats__count}>
              <strong>0</strong>
              {t('SEARCH:results-count')}
            </span>
          </div>
        )}
        defaultQuery={customQuery}
        render={({ data, rawData, resultStats }) => (
          <>
            <SortSelect
              setSort={setSort}
            />
            <StateProvider includeKeys={['value']} render={({ searchState }) => (
              <>
                {searchState[SearchComponents.SEARCH_BAR]?.value && isOperatorSearch(searchState[SearchComponents.SEARCH_BAR]?.value) &&
                  <Notification
                    label={t('SEARCH:operators-enabled-label')}
                    notificationAriaLabel={t('SEARCH:operators-enabled-label')}
                    size="small"
                    className={styles.ResultsContainer__status}
                  >
                    {t('SEARCH:operators-enabled')} {operatorGuideUrl && (
                      <>
                        <a href={operatorGuideUrl} target="_blank" rel="noopener noreferrer">{t('SEARCH:operators-enabled-read-more')}</a>.
                      </>
                    )}
                  </Notification>
                }
              </>
            )} />
            <ReactiveList.ResultCardsWrapper
              style={cardWrapperStyles}
            >
              {
                data.map((item: any) => {
                  // Item mapping.
                  const {id} = item;
                  // Check document count for collapsed search results.
                  const aggregations = rawData.aggregations;
                  let doc_count = 1;

                  if (item.unique_issue_id && item.unique_issue_id[0] && aggregations && aggregations.unique_issue_id && aggregations.unique_issue_id.buckets.length) {
                    const buckets = aggregations.unique_issue_id.buckets;
                    for (let i = 0; i < buckets.length; i++) {
                      if (buckets[i].key === item.unique_issue_id[0]) {
                        doc_count = buckets[i].doc_count;
                      }
                    }
                  }

                  const resultProps = {
                    category: item.top_category_name,
                    color_class: item.color_class,
                    organization_name: item.organization_name,
                    date: item.meeting_date,
                    href: item.decision_url,
                    lang_prefix: t('SEARCH:prefix'),
                    url_prefix: t('DECISIONS:url-prefix'),
                    url_query: t('DECISIONS:url-query'),
                    amount_label: t('DECISIONS:amount-label'),
                    issue_id: item.issue_id,
                    unique_issue_id: item.unique_issue_id,
                    doc_count: doc_count,
                    policymaker: '',
                    subject: item.subject,
                    issue_subject: item.issue_subject,
                    _score: item._score
                  };
                  return <ResultCard
                    key={id.toString()}
                    {...resultProps}
                  />
                })
              }
              {data.length % 3 !== 0 &&
                <PhantomCard />
              }
            </ReactiveList.ResultCardsWrapper>
          </>
        )}
      />
    </div>
  );
};

export default ResultsContainer;
