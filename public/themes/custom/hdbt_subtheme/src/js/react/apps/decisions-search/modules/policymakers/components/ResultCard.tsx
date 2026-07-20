import CardItem, { Metarow } from '@/react/common/Card';
import { getOrganizationCategoryTag } from '../../../common/utils/getOrganizationCategoryTag';

type ResultCardProps = {
  field_organization_type?: string[];
  field_policymaker_id?: string[];
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
  field_organization_type,
  field_policymaker_id,
  title,
  trustee_name,
  trustee_title,
  url,
  organization_hierarchy,
}: ResultCardProps) => {
  const cardTitle = trustee_name?.[0] || title?.[0] || '';
  const cardUrl = url?.[0] ? getLocalizedUrl(url[0]) : '';
  const categoryTag = getOrganizationCategoryTag(field_policymaker_id?.[0], field_organization_type?.[0]);

  const organizationHierarchy = organization_hierarchy?.length
    ? {
        bottom: [
          <Metarow
            key='organization-hierarchy'
            icon='sitemap'
            label={Drupal.t('Organisation structure', {}, { context: 'Policymakers search' })}
            content={
              <span className='policymaker-search-result-card__hierarchy'>
                {organization_hierarchy.map((org) => (
                  <span key={org} className='policymaker-search-result-card__hierarchy__item'>
                    {org}
                  </span>
                ))}
              </span>
            }
          />,
        ],
      }
    : undefined;

  return (
    <CardItem
      cardCategoryTag={categoryTag}
      cardDescription={trustee_title?.[0] ? getTranslatedTrusteeTitle(trustee_title[0]) : undefined}
      cardModifierClass='policymaker-search-result-card'
      cardTitle={cardTitle}
      cardUrl={cardUrl}
      customMetaRows={organizationHierarchy}
    />
  );
};
