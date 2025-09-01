import React from 'react';
import { IconArrowRight, IconSize } from 'hds-react';
import { format } from 'date-fns';
import CardItem from '@/react/common/Card';
import classNames from 'classnames';
import useDepartmentClasses from '../../../../hooks/useDepartmentClasses';
import MetadataType from "@/types/MetadataType";


type Props = {
  category: string,
  color_class: string[],
  date: number,
  href: string,
  lang_prefix: string,
  url_prefix: string,
  url_query: string,
  amount_label: string,
  issue_id: string,
  unique_issue_id: string,
  doc_count: number,
  subject: string,
  issue_subject: string,
  _score: number,
  organization_name: string
  organization_type: string
};

const ResultCard = ({ category, color_class, date, href, lang_prefix, url_prefix, url_query, amount_label, issue_id, unique_issue_id, doc_count, organization_name, organization_type, subject, issue_subject, _score }: Props) => {
  // const colorClass = useDepartmentClasses(color_class);

  console.log(organization_type);

  let url = '';
  if (typeof href !== 'undefined') {
    url = href.toString();
    if (url.startsWith('/en/')) {
      url = url.replace('/en/', lang_prefix).replace('case', url_prefix).replace('decision', url_query);
    }
    else if (url.startsWith('/sv/')) {
      url = url.replace('/sv/', lang_prefix).replace('arende', url_prefix).replace('beslut', url_query);
    }
    else {
      url = url.replace('/fi/', lang_prefix).replace('asia', url_prefix).replace('paatos', url_query);
    }
  }

  let formattedDate;
  if (date) {
    formattedDate = format(new Date(date * 1000), 'dd.MM.yyyy');
  }

  return (
    <CardItem
      cardTitle={subject}
      cardUrl={url}
      cardMetas={[
        organization_name && {
          icon: 'user',
          label: Drupal.t('Decision maker', {}, { context: 'React search'}),
          content: organization_name,
        },
        formattedDate && {
          icon: 'calendar',
          label: Drupal.t('Date', {}, { context: 'React search'}),
          content: formattedDate,
        },
        issue_subject && {
          icon: 'layers',
          label: Drupal.t('Decision case with multiple decisions', {}, { context: 'React search'}),
          content: issue_subject,
        },
      ].filter(Boolean) as MetadataType[]}
    />
  );
};

export default ResultCard;
