import { DateTime } from 'luxon';
import { IconAlertCircle, IconLayers, IconUser } from 'hds-react';

import type { Decision } from '../../../common/types/Decision';
import { HDS_DATE_FORMAT } from '@/react/common/enum/HDSDateFormat';
import CardItem, { Metarow } from '@/react/common/Card';
import { OrganizationTypes } from '../enum/OrganizationTypes';
import type TagType from '@/common/types/TagType';
import { Policymakers } from '../enum/Policymakers';

export const ResultCard = ({
  _category_name,
  decision_url,
  field_is_decision,
  field_policymaker_id,
  _has_multiple_decisions = false,
  issue_subject,
  meeting_date,
  more_decisions,
  organization_name,
  organization_type,
  subject,
  _unique_issue_id,
}: Decision) => {
  const getDate = () => {
    if (!meeting_date.toString().length) {
      return '';
    }

    const date = DateTime.fromISO(meeting_date.toString(), { zone: 'utc' });

    if (!date.isValid) {
      return '';
    }

    return date.toFormat(HDS_DATE_FORMAT);
  };

  const getCategoryTag = () => {
    const tag: TagType = {};

    const policymakerId = field_policymaker_id?.toString();
    const type = organization_type?.toString();
    if (policymakerId === Policymakers.CITY_COUNCIL) {
      tag.color = 'copper';
      tag.tag = Drupal.t('City Council', {}, { context: 'Decisions search' });
    } else if (policymakerId === Policymakers.CITY_HALL) {
      tag.color = undefined;
      tag.tag = Drupal.t('City Hall', {}, { context: 'Decisions search' });
    } else if (
      type === OrganizationTypes.BOARD ||
      type === OrganizationTypes.DIVISION ||
      type === OrganizationTypes.OPERATIONAL_BOARD ||
      type === OrganizationTypes.TEAM
    ) {
      tag.color = 'coat-of-arms';
      tag.tag = Drupal.t(
        'Committees and boards',
        {},
        { context: 'Decisions search' },
      );
    } else if (type === OrganizationTypes.OFFICIAL) {
      tag.color = 'gold';
      tag.tag = Drupal.t('Officials', {}, { context: 'Decisions search' });
    } else if (type === OrganizationTypes.TRUSTEE) {
      tag.color = 'bus';
      tag.tag = Drupal.t('Trustees', {}, { context: 'Decisions search' });
    } else {
      return undefined;
    }

    return tag;
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
  };

  if (more_decisions?.[0] === true) {
    metaRows.bottom = [
      <Metarow
        key='1'
        icon={<IconLayers className='hel-icon' />}
        label={Drupal.t(
          'Issues with several decisions',
          {},
          { context: 'Decisions search' },
        )}
        content={issue_subject}
      />,
    ];
  }

  const getMotionTag = () => {
    if (field_is_decision?.[0]) {
      return;
    }

    return [
      {
        color: 'alert',
        iconStart: <IconAlertCircle className='hel-icon' />,
        tag: Drupal.t('This is a motion'),
      },
    ];
  };

  return (
    <CardItem
      cardCategoryTag={getCategoryTag()}
      cardTags={getMotionTag()}
      cardTitle={subject || issue_subject}
      cardUrl={decision_url}
      customMetaRows={metaRows}
      date={getDate()}
    />
  );
};
