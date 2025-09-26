export const IndexFields = {
  // TODO: Change to search_api_language.
  // _language is a custom field. Elasticsearch connector
  // creates search_api_language by default.
  LANGUAGE: '_language',
  CONTENT_DRAFT_PROPOSAL: 'content_draft_proposal',
  CONTENT_PRESENTER: 'content_presenter',
  CONTENT_RESOLUTION: 'content_resolution',
  DECISION_CONTENT: 'decision_content',
  DECISION_MOTION: 'decision_motion',
  IS_DECISION: 'field_is_decision',
  ISSUE_ID: 'issue_id',
  UNIQUE_ISSUE_ID: 'unique_issue_id',
  ISSUE_SUBJECT: 'issue_subject',
  MEETING_DATE: 'meeting_date',
  MEETING_POLICYMAKER_LINK: 'meeting_policymaker_link',
  SUBJECT: 'subject',
  SUBJECT_RESOLUTION: 'subject_resolution',
  TOP_CATEGORY_NAME: 'top_category_name',
  TOP_CATEGORY_ID: 'top_category_id',
  SCORE: '_score',
  ORG_NAME: 'organization_name',
  ORG_TYPE: 'organization_type',
  SECTOR: 'sector',
  SECTOR_ID: 'sector_id',
  HAS_TRANSLATION: 'has_translation',
  POLICYMAKER_ID: 'field_policymaker_id',
  POLICYMAKER_STRING: 'decisionmaker_searchfield_data',
};

export default IndexFields;
