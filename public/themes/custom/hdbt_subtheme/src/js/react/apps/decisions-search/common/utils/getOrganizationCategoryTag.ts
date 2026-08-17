import type TagType from '@/types/TagType';
import { OrganizationTypes } from '../enum/OrganizationTypes';
import { Policymakers } from '../enum/Policymakers';

export const getOrganizationCategoryTag = (policymakerId?: string, organizationType?: string): TagType | undefined => {
  if (policymakerId === Policymakers.CITY_COUNCIL) {
    return { color: 'copper', tag: Drupal.t('City Council', {}, { context: 'Decisions search' }) };
  }

  if (policymakerId === Policymakers.CITY_HALL) {
    return { color: undefined, tag: Drupal.t('City Hall', {}, { context: 'Decisions search' }) };
  }

  if (
    organizationType === OrganizationTypes.BOARD ||
    organizationType === OrganizationTypes.DIVISION ||
    organizationType === OrganizationTypes.OPERATIONAL_BOARD ||
    organizationType === OrganizationTypes.TEAM
  ) {
    return { color: 'coat-of-arms', tag: Drupal.t('Committees and boards', {}, { context: 'Decisions search' }) };
  }

  if (organizationType === OrganizationTypes.OFFICIAL) {
    return { color: 'gold', tag: Drupal.t('Officials', {}, { context: 'Decisions search' }) };
  }

  if (organizationType === OrganizationTypes.TRUSTEE) {
    return { color: 'bus', tag: Drupal.t('Trustees', {}, { context: 'Decisions search' }) };
  }

  return undefined;
};
