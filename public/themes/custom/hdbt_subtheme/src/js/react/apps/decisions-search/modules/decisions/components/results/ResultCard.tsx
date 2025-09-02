import React from 'react';
import { IconAlertCircle } from 'hds-react';
import { format } from 'date-fns';
import CardItem from '@/react/common/Card';
import MetadataType from "@/types/MetadataType";

type Props = {
  date: number,
  href: string,
  lang_prefix: string,
  url_prefix: string,
  url_query: string,
  issue_id: string,
  unique_issue_id: string,
  doc_count: number,
  subject: string,
  issue_subject: string,
  _score: number,
  organization_name: string,
  organization_type: string,
  field_is_decision: string[],
};

const ResultCard = ({ date, href, lang_prefix, url_prefix, url_query, issue_id, unique_issue_id, doc_count, organization_name, organization_type, field_is_decision, subject, issue_subject, _score }: Props) => {

  // This is really nasty and needs some kind of real implementation and localization.
  const cardCategoryColorMap: Record<string, string> = {
    'Lautakunta': 'coat-of-arms',
    'Luottamushenkilö': 'gold',
    'Viranhaltija': 'bus',
    'Kaupunginhallitus': 'silver',
    'Kaupunginvaltuusto': 'copper',
  };

  // Get the organization color based on the mapping of names and colors.
  function getColorForOrgType(organization_type?: string): string {
    return cardCategoryColorMap[organization_type ?? ''] || 'engel';
  }

  // URL where the card should take the user.
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

  // Formatting the date of the case.
  let formattedDate;
  if (date) {
    formattedDate = format(new Date(date * 1000), 'dd.MM.yyyy');
  }

  // Check if the field_is_decision is true. If yes, the case is no longer a motion.
  let thisIsMotion = true;
  if (field_is_decision[0]) {
    thisIsMotion = false;
  }

  // Debug information in readable format.
  const debugInformation = `
    <b>Score:</b> ${_score},<br/>
    <b>Diary number:</b> ${issue_id},<br/>
    <b>Unique issue ID:</b> ${unique_issue_id},<br/>
    <b>Doc Count:</b> ${doc_count}<br/>
    <b>URL:</b> ${href}
  `;

  return (
    <CardItem
      cardTitle={subject}
      cardUrl={url}
      {...(organization_type && {
        cardCategoryTag: {
          tag: organization_type,
          color: getColorForOrgType(organization_type),
        }
      })}
      cardMetas={[
        organization_name && {
          icon: 'user',
          label: Drupal.t('Decision maker', {}, { context: 'Decision search'}),
          content: organization_name,
        },
        formattedDate && {
          icon: 'calendar',
          label: Drupal.t('Date', {}, { context: 'Decision search'}),
          content: formattedDate,
        },
        issue_subject && {
          icon: 'layers',
          label: Drupal.t('Decision case with multiple decisions', {}, { context: 'Decision search'}),
          content: issue_subject,
        },
      ].filter(Boolean) as MetadataType[]}
      {...(thisIsMotion && {
        cardTags: [
          {
            tag: Drupal.t('This is a motion', {}, { context: 'Decision search'}),
            color: 'alert',
            icon: <IconAlertCircle />,
          },
        ],
      })}
      {...(_DEBUG_MODE_ && { cardDescription: debugInformation })}
      {...(_DEBUG_MODE_ && { cardDescriptionHtml: true })}
    />
  );
};

export default ResultCard;
