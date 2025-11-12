export type Decision = {
  address?: [string];
  address_fulltext?: [string];
  approval_type: Array<'WORK_FINISHED' | 'OPERATIONAL_CONDITION'>;
  document_created: [number];
  document_type: Array<
    | 'EXCAVATION_ANNOUNCEMENT'
    | 'AREA_RENTAL'
    | 'AREA_RENTAL'
    | 'TEMPORARY_TRAFFIC_ARRANGEMENTS'
    | 'PLACEMENT_CONTRACT'
    | 'EVENT'
    | 'SHORT_TERM_RENTAL'
  >;
  label: [string];
  search_api_datasource: [string];
  search_api_id: [string];
  search_api_language: [string];
  url: [string];
};
