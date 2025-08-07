export const IndexFields = {
  TITLE: 'title',
  COMBINED_TITLE: 'decisionmaker_combined_title',
  // TODO: Change to search_api_language.
  // _language is a custom field. Elasticsearch connector
  // creates search_api_language by default.
  LANGUAGE: '_language',
  ORGANIZATION_TYPE: 'field_organization_type.keyword',
  SECTOR: 'field_sector_name',
  HREF: 'search_api_url',
  ORGAN: 'organ',
  COLOR_CLASS: 'color_class',
  DM_FIRST_NAME: 'field_first_name',
  DM_LAST_NAME: 'field_last_name',
  TRUSTEE_NAME: 'trustee_name',
  TRUSTEE_TITLE: 'trustee_title',
  HAS_TRANSLATION: 'has_translation',
  POLICYMAKER_EXISTING: 'field_policymaker_existing'
};

export default IndexFields;
