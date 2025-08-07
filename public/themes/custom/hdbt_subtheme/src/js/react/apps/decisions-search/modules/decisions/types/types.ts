export type FormErrors = {
  from?: string,
  to?: string
};

export type Fields = {
  top_category_name: string,
  meeting_date: string,
  decision_url: string,
  policymaker: string,
  subject: string
};

export type Option = {
  label: string,
  value: string
};

export type Options = Array<Option>;

export type aggregate = {
  id: string,
  sector: {[key: string]: string},
  organization: {[key: string]: string},
  organization_above: {[key: string]: string}
};

export type combobox_item = {
  label: string,
  sort_label: string,
  key: string,
  value: string
}