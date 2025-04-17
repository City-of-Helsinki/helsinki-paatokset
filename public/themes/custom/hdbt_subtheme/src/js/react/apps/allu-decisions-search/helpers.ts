import { estypes } from '@elastic/elasticsearch';
import { DateTime } from 'luxon';

import { type Selections } from './types/Selections';
import { HDS_DATE_FORMAT } from '@/react/common/enum/HDSDateFormat';

/**
 * Convert type label to human readable.
 * 
 * @param {string} type - Decision type 
 * @return {string} - Translated label
 */
export const matchTypeLabel = (type: string) => {
  switch (type) {
    case 'EXCAVATION_ANNOUNCEMENT':
      return Drupal.t('Excavation announcement', {}, { context: 'Allu decision search' });
    case 'AREA_RENTAL':
      return Drupal.t('Area rental', {}, { context: 'Allu decision search' });
    case 'TEMPORARY_TRAFFIC_ARRANGEMENTS':
      return Drupal.t('Temporary traffic announcement', {}, { context: 'Allu decision search' });
    case 'PLACEMENT_CONTRACT':
      return Drupal.t('Placement contract', {}, { context: 'Allu decision search' });
    case 'EVENT':
      return Drupal.t('Event', {}, { context: 'Allu decision search' });
    case 'SHORT_TERM_RENTAL':
      return Drupal.t('Short term rental', {}, { context: 'Allu decision search' });
    default:
      throw new Error('Unknown decision type');
  };
};

/**
 * Match human readable name of type to value.
 * 
 * @param {string} label - Decision type label
 * @return {string} - The value
 */
export const matchTypeValueFromLabel = (label: string) => {
  switch (label) {
    case Drupal.t('Excavation announcement', {}, { context: 'Allu decision search' }):
      return 'EXCAVATION_ANNOUNCEMENT';
      case Drupal.t('Area rental', {}, { context: 'Allu decision search' }):
        return 'AREA_RENTAL';
      case Drupal.t('Temporary traffic announcement', {}, { context: 'Allu decision search' }):
        return 'TEMPORARY_TRAFFIC_ARRANGEMENTS';
      case Drupal.t('Placement contract', {}, { context: 'Allu decision search' }):
        return 'PLACEMENT_CONTRACT';
      case Drupal.t('Event', {}, { context: 'Allu decision search' }):
        return 'EVENT';
      case Drupal.t('Short term rental', {}, { context: 'Allu decision search' }):
        return 'SHORT_TERM_RENTAL';
      default:
        throw new Error('Unknown decision type label');
  };
};

/**
 * Form query for Elastic.
 * 
 * @param {object} selections - user filter selections 
 * @param {boolean} includeAggs - wether to include aggs for filter options
 *  
 * @return {object} - The result 
 */
export const formQuery = (selections: Selections) => {
  const body: estypes.AsyncSearchSubmitRequest = {
    query: {
      bool: {
      },
    }
  };

  const must: estypes.QueryDslQueryContainer[] = [
    {
      match: {
        // Match only allu documents
        search_api_datasource: 'entity:paatokset_allu_document',
      },
    }
  ];

  if (selections?.type?.length) {
    must.push({
      terms: {
        document_type: selections.type.map(type => type.value)
      }
    });
  }
  
  const range: estypes.QueryDslRangeQuery = {};
  
  if (selections.start) {
    range.gte = DateTime.fromFormat(selections.start, HDS_DATE_FORMAT).startOf('day').toUnixInteger().toString();
  }
  if (selections.end) {
    range.lte = DateTime.fromFormat(selections.end, HDS_DATE_FORMAT).endOf('day').toUnixInteger().toString();
  }
  
  if (Object.keys(range).length && body?.query?.bool) {
    must.push({
      range: {
        document_created: range,
      }
    });
  }

  if (body.query?.bool) {
    body.query.bool.must = must;
  }

  let should: estypes.QueryDslQueryContainer[] = [];

  if (selections.q) {
    should = should.concat([
      {
        match_phrase_prefix: {
          address_fulltext: selections.q,
        }
      },
      {
        match: {
          label: selections.q,
        }
      }
    ]);
  }

  if (should.length && body?.query?.bool) {
    body.query.bool.should = should;
    body.query.bool.minimum_should_match = 1;
  }

  const sort: estypes.SortCombinations[] = [
    {
      document_created: {
        order: 'desc'
      }
    }
  ];

  const { page, ...rest } = selections;
  if (Object.keys(rest).length) {
    sort.unshift({
      _score: {
        order: 'desc'
      }
    });
  }

  body.sort = sort;

  return body;
};
