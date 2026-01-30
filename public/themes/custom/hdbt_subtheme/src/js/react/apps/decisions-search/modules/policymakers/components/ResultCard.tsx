import { IconArrowRight, IconAngleRight } from 'hds-react';
import type { PolicyMaker } from '../../../common/types/PolicyMaker';

type ResultCardProps = {
  color_class?: string[];
  title?: string[];
  trustee_name?: string[];
  trustee_title?: string[];
  url?: string[];
  organization_hierarchy?: string[];
};

const getTranslatedTrusteeTitle = (trusteeTitle: string): string => {
  if (trusteeTitle === 'Councillor') {
    return Drupal.t('Councillor', {}, { context: 'Policymakers search' });
  }
  if (trusteeTitle === 'Deputy councillor') {
    return Drupal.t('Deputy councillor', {}, { context: 'Policymakers search' });
  }
  return trusteeTitle;
};

const getLocalizedUrl = (url: string): string => {
  const { currentLanguage } = drupalSettings.path;
  let localizedUrl = url;

  const prefixMap: Record<string, string> = {
    fi: 'paattajat',
    sv: 'beslutsfattare',
    en: 'decisionmakers',
  };

  const targetPrefix = prefixMap[currentLanguage] || prefixMap.fi;

  // Replace language prefix
  localizedUrl = localizedUrl.replace(/^\/(fi|sv|en)\//, `/${currentLanguage}/`);

  // Replace policymaker URL segment
  for (const prefix of Object.values(prefixMap)) {
    if (localizedUrl.includes(prefix)) {
      localizedUrl = localizedUrl.replace(prefix, targetPrefix);
      break;
    }
  }

  return localizedUrl;
};

export const ResultCard = ({
  color_class,
  title,
  trustee_name,
  trustee_title,
  url,
  organization_hierarchy,
}: ResultCardProps) => {
  const colorClass = color_class?.[0] || '';
  const cardTitle = trustee_name?.[0] || title?.[0] || '';
  const cardUrl = url?.[0] ? getLocalizedUrl(url[0]) : '';

  const renderOrganizationHierarchy = () => {
    if (!organization_hierarchy?.length) {
      return null;
    }

    return (
      <div className='policymaker-row__sub-title'>
        {organization_hierarchy.map((org, index) => (
          <span key={org}>
            {index !== 0 && <IconAngleRight />}
            {org}
          </span>
        ))}
      </div>
    );
  };

  return (
    <article className='policymaker-search-result-card'>
      <a href={cardUrl} data-color-class={colorClass} className='policymaker-row__link'>
        <span className='policymaker-row__color' style={{ backgroundColor: colorClass }} />
        <div className='policymaker-row__title'>
          {cardTitle}
          {trustee_title?.[0] && (
            <div className='policymaker-row__sub-title'>{getTranslatedTrusteeTitle(trustee_title[0])}</div>
          )}
          {renderOrganizationHierarchy()}
        </div>
        <IconArrowRight className='hel-icon' />
      </a>
    </article>
  );
};
