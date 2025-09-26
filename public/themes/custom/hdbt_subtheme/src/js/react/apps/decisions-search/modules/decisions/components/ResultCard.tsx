import { DateTime } from 'luxon';
import { IconUser } from 'hds-react';

import { Decision } from '../../../common/types/Decision';
import { HDS_DATE_FORMAT } from '@/react/common/enum/HDSDateFormat';
import CardItem, { Metarow } from '@/react/common/Card';
import { OrganizationTypes } from '../enum/OrganizationTypes';
import TagType from '@/common/types/TagType';

export const ResultCard = ({
  category_name,
  decision_url,
  issue_subject,
  meeting_date,
  organization_name,
  organization_type,
  subject,
  unique_issue_id,
}: Decision) => {


  const getDate = () => {
    if (!meeting_date.toString().length) {
      return '';
    }

    const date = DateTime.fromISO(meeting_date.toString(), {zone: 'utc'});

    if (!date.isValid) {
      return '';
    }

    return date.toFormat(HDS_DATE_FORMAT);
  };

  const getCategoryTag = () => {
    const tag: TagType = {
    };

    const type = organization_type?.[0];
    if (
      type === OrganizationTypes.BOARD ||
      type === OrganizationTypes.DIVISION ||
      type === OrganizationTypes.OPERATIONAL_BOARD ||
      type === OrganizationTypes.TEAM
    ) {
      tag.color = 'coat-of-arms';
      tag.tag = Drupal.t('Boards');
    }
    else if (type === OrganizationTypes.COUNCIL) {
      tag.color = 'copper';
      tag.tag = Drupal.t('City council');
    }
    else if (type === OrganizationTypes.POLICYMAKER) {
      tag.color = 'bus';
      tag.tag = Drupal.t('Policy makers');
    }
    else if (type === OrganizationTypes.GOVERNMENT) {
      tag.color = undefined;
      tag.tag = Drupal.t('City Government');
    }
    else if (type === OrganizationTypes.TRUSTEE) {
      tag.color = 'gold';
      tag.tag = Drupal.t('Trustees');
    }
    else {
      return undefined;
    }

    return tag;
  };

  return (
    <CardItem
      cardCategoryTag={getCategoryTag()}
      cardTitle={issue_subject || subject}
      cardUrl={decision_url}
      customMetaRows={[
        <Metarow
          key='0'
          icon={<IconUser className='hel-icon' />}
          label={Drupal.t('Decision maker')}
          content={organization_name}
        />
      ]}
      date={getDate()}
    />
  );
};
