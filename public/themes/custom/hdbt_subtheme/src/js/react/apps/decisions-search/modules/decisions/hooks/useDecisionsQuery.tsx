import { DateTime } from 'luxon';
import { useAtomValue } from 'jotai';
import { type estypes } from '@elastic/elasticsearch';
import { useMemo } from 'react';

import { submittedStateAtom } from '../store';
import { Components } from '../enum/Components';
import { DecisionIndex } from '../enum/IndexFields';
import { isOperatorSearch } from '../../../common/utils/OperatorSearch';
import { HDS_DATE_FORMAT } from '@/react/common/enum/HDSDateFormat';
import { SortOptions } from '../enum/SortOptions';
import { getAdvancedBoostQuery, getBaseSearchTermQuery } from '../../../common/utils/Query';

// Boosted fields for full text search
const dataFields = [
  `${DecisionIndex.SUBJECT}^5`,
  `${DecisionIndex.ISSUE_SUBJECT}^2`,
  DecisionIndex.DECISION_CONTENT,
  DecisionIndex.DECISION_MOTION,
];

// Fields to be returned in search results
const fields = [
  DecisionIndex.CATEGORY_NAME,
  DecisionIndex.DECISION_URL,
  DecisionIndex.POLICYMAKER_ID,
  DecisionIndex.IS_DECISION,
  DecisionIndex.ID,
  DecisionIndex.ISSUE_SUBJECT,
  DecisionIndex.MEETING_DATE,
  DecisionIndex.MORE_DECISIONS,
  DecisionIndex.ORG_NAME,
  DecisionIndex.ORG_TYPE,
  DecisionIndex.SUBJECT,
  DecisionIndex.UNIQUE_ISSUE_ID,
];

export const useDecisionsQuery = (customSearchTerm: string): estypes.QueryDslQueryContainer => {
  const submittedState = useAtomValue(submittedStateAtom);

  return useMemo(() => {
    const filter: estypes.QueryDslQueryContainer[] = [
      {
        exists: {
          field: DecisionIndex.MEETING_DATE
        },
      },
    ]; 
    const should: estypes.QueryDslQueryContainer[] = [];

    const getSearchTerm = () => {
      if (customSearchTerm) {
        return customSearchTerm;
      }
      return submittedState[Components.SEARCHBAR] && submittedState[Components.SEARCHBAR].trim();
    };

    const searchTerm = getSearchTerm();
    if (searchTerm && searchTerm.length) {
      getBaseSearchTermQuery(searchTerm, dataFields).forEach(item => should.push(item));
      getAdvancedBoostQuery(searchTerm, DecisionIndex.SUBJECT).forEach(item => should.push(item));
      should.push({
        wildcard: {
          [DecisionIndex.SUBJECT]: {
            value: `*${searchTerm.toLowerCase()}*`,
          }
        }
      });
    }

    if (searchTerm && isOperatorSearch(searchTerm))  {
      filter.push({
        simple_query_string: {
          query: searchTerm,
          default_operator: 'or',
          analyze_wildcard: true,
        }
      });
    }

    const categoryFilter = submittedState[Components.CATEGORY];
    if (categoryFilter.length) {
      filter.push({
        terms: {
          'top_category_code': categoryFilter.map((category) => category.value)
        }
      });
    }

    const fromFilter = submittedState[Components.FROM];
    const toFilter = submittedState[Components.TO];
    const dateQuery = {
      range: {
        meeting_date: {}
      }
    };

    [fromFilter, toFilter].forEach((date, index) => {
      if (date) {
        dateQuery.range.meeting_date[index === 0 ? 'gte' : 'lte'] = DateTime.fromFormat(date, HDS_DATE_FORMAT).toFormat('yyyy-MM-dd');
      }
    });

    if (
      dateQuery.range.meeting_date.gte ||
      dateQuery.range.meeting_date.lte
    ) {
      filter.push(dateQuery);
    }

    const dmSelection = submittedState[Components.DECISIONMAKER];
    if (dmSelection.length) {
      filter.push({
        terms: {
          [DecisionIndex.POLICYMAKER_ID]: dmSelection.map((dm) => dm.value)
        }
      });
    }

    const query: estypes.QueryDslQueryContainer = {
      bool: {
        filter,
      }
    };

    if (should.length) {
      query.bool.should = should;
      query.bool.minimum_should_match = 1;
    }

    const size = 10;
    const page = submittedState.page || 1;

    const sort = [];
    const sortSelection = submittedState[Components.SORT];
    switch (sortSelection) {
      case SortOptions.OLDEST:
        sort.push({ [DecisionIndex.MEETING_DATE]: 'asc' });
        break;
      case SortOptions.NEWEST:
        sort.push({ [DecisionIndex.MEETING_DATE]: 'desc' });
        break;
      default:
        sort.push({ _score: 'desc' });
        sort.push({ [DecisionIndex.MEETING_DATE]: 'desc' });
        break;
    };

    const { currentLanguage } = drupalSettings.path;
    const preferredLanguage = currentLanguage === 'sv' ? 'sv' : 'fi';
    const innerHitSort = [{
      _script: {
        type: 'number',
        script: {
          lang: 'painless',
          source: `doc['${DecisionIndex.SEARCH_API_LANGUAGE}'].value == '${preferredLanguage}' ? 0 : 1`
        },
        order: 'asc',
      },
    }];
    switch (sortSelection) {
      case SortOptions.OLDEST:
        innerHitSort.unshift({ [DecisionIndex.MEETING_DATE]: 'asc' });
        break;
      case SortOptions.NEWEST:
        innerHitSort.unshift({ [DecisionIndex.MEETING_DATE]: 'desc' });
        break;
      default:
        innerHitSort.unshift({ [DecisionIndex.MEETING_DATE]: 'desc' });
        innerHitSort.unshift({ _score: 'desc' });
        break;
    }

    const result = {
      _source: false,
      aggs: {
        total_issues: {
          cardinality: {
            field: DecisionIndex.UNIQUE_ISSUE_ID,
          },
        }
      },
      collapse: {
        field: DecisionIndex.UNIQUE_ISSUE_ID,
        inner_hits: {
          _source: false,
          fields,
          name: 'preferred_version',
          sort: innerHitSort,
        },
      },
      from: size * (page - 1),
      query: {
        function_score: {
          query,
          functions: [{
            gauss: {
              [DecisionIndex.MEETING_DATE]: {
                decay: 0.3,
                origin: 'now',
                scale: '60d',
              },
            },
          }],
          boost_mode: 'sum',
          score_mode: 'sum',  
        },
      },
      size,
      sort,
      track_total_hits: true,
    };

    if (customSearchTerm) {
      return result;
    }

    return JSON.stringify(result);
  }, [submittedState, customSearchTerm]);
};
