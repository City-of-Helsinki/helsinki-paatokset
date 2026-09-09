import { IconAlertCircle, IconLayers, IconUser } from 'hds-react';
import CardItem, { Metarow } from '@/react/common/Card';
import { formatHDSDateUTC } from '@/react/common/helpers/dateUtils';
import { Policymakers } from '../../../common/enum/Policymakers';
import type { Decision } from '../../../common/types/Decision';
import { getOrganizationCategoryTag } from '../../../common/utils/getOrganizationCategoryTag';

// Decisions are rarely translated, so decision_url mostly points to the
// content's original language. Rewrite it to use the current language's
// prefix, path segment and query key instead, since the case/decision
// route can render untranslated content with a translated UI.
const casePathSegments: Record<string, string> = { fi: 'asia', sv: 'arende', en: 'case' };
const decisionQueryKeys: Record<string, string> = { fi: 'paatos', sv: 'beslut', en: 'decision' };

const getLocalizedUrl = (url: string): string => {
  const { currentLanguage } = drupalSettings.path;

  if (!/^\/(fi|sv|en)\//.test(url)) {
    return url;
  }

  let localizedUrl = url.replace(/^\/(fi|sv|en)\//, `/${currentLanguage}/`);

  const targetSegment = casePathSegments[currentLanguage] || casePathSegments.fi;
  for (const segment of Object.values(casePathSegments)) {
    if (localizedUrl.startsWith(`/${currentLanguage}/${segment}/`)) {
      localizedUrl = localizedUrl.replace(`/${currentLanguage}/${segment}/`, `/${currentLanguage}/${targetSegment}/`);
      break;
    }
  }

  const targetQueryKey = decisionQueryKeys[currentLanguage] || decisionQueryKeys.fi;
  for (const key of Object.values(decisionQueryKeys)) {
    if (localizedUrl.includes(`?${key}=`)) {
      localizedUrl = localizedUrl.replace(`?${key}=`, `?${targetQueryKey}=`);
      break;
    }
  }

  return localizedUrl;
};

export const ResultCard = ({
  decision_url,
  field_is_decision,
  field_policymaker_id,
  issue_subject,
  meeting_date,
  more_decisions,
  organization_name,
  organization_type,
  subject,
}: Decision) => {
  const getDate = () => {
    if (!meeting_date.toString().length) {
      return '';
    }

    const date = new Date(meeting_date.toString());

    if (Number.isNaN(date.getTime())) {
      return '';
    }

    return formatHDSDateUTC(date);
  };

  const metaRows = {
    top: [
      <Metarow
        key='0'
        icon={<IconUser className='hel-icon' />}
        label={Drupal.t('Decision-maker', {}, { context: 'Decisions search' })}
        content={organization_name}
      />,
    ],
    bottom: [] as React.ReactElement[],
  };

  if (
    field_policymaker_id?.toString() === Policymakers.CITY_COUNCIL ||
    field_policymaker_id?.toString() === Policymakers.CITY_HALL
  ) {
    metaRows.top = [];
  }

  if (more_decisions?.[0] === true) {
    metaRows.bottom = [
      <Metarow
        key='1'
        icon={<IconLayers className='hel-icon' />}
        label={Drupal.t('Issues with several decisions', {}, { context: 'Decisions search' })}
        content={issue_subject}
      />,
    ];
  }

  const getMotionTag = () => {
    if (field_is_decision?.[0]) {
      return;
    }

    return [{ color: 'alert', iconStart: <IconAlertCircle className='hel-icon' />, tag: Drupal.t('This is a motion') }];
  };

  return (
    <CardItem
      cardCategoryTag={getOrganizationCategoryTag(field_policymaker_id?.toString(), organization_type?.toString())}
      cardTags={getMotionTag()}
      cardTitle={subject?.[0] || issue_subject?.[0]}
      cardUrl={decision_url?.[0] ? getLocalizedUrl(decision_url[0]) : undefined}
      customMetaRows={metaRows}
      date={getDate()}
    />
  );
};
