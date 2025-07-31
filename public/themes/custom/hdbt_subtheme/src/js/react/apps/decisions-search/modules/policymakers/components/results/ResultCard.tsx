import { FC } from 'react'
import { IconArrowRight, IconAngleRight } from 'hds-react';
import { useTranslation } from 'react-i18next';
import useDepartmentClasses from '../../../../hooks/useDepartmentClasses';

type Props = {
  color_class: string[],
  title: string,
  trustee_name?: string,
  trustee_title?: string,
  url?: string,
  organization_hierarchy: string[]
}

const ResultCard: FC<Props> = ({color_class, title, trustee_name, trustee_title, url, organization_hierarchy}) => {
  const { t } = useTranslation();
  const colorClass = useDepartmentClasses(color_class);
  const translatedTrusteeTitle = (trustee_title:string) => {
    if (trustee_title.toString() === 'Councillor') {
      return t('POLICYMAKERS:councillor');
    }
    else if (trustee_title.toString() === 'Deputy councillor') {
      return t('POLICYMAKERS:deputy-councillor');
    }
    else {
      return trustee_title;
    }
  }

  const formattedOrganizations = (organization_hierarchy:string[]) => (
    organization_hierarchy.map(
      (organization, index) => (  
        <span key={index}>{index !== 0 ? <IconAngleRight /> : ''}{organization}</span>
      )
    )
  )

  if (typeof url !== 'undefined') {
    url = url.toString();
    if (url.includes('/fi/')) {
      url = url.toString().replace('/fi/', t('SEARCH:prefix')).replace('paattajat', t('POLICYMAKERS:url-prefix'));
    }
    else if (url.includes('/sv/')) {
      url = url.toString().replace('/sv/', t('SEARCH:prefix')).replace('beslutsfattare', t('POLICYMAKERS:url-prefix'));
    }
    else if (url.includes('/en/')) {
      url = url.toString().replace('/en/', t('SEARCH:prefix')).replace('decisionmakers', t('POLICYMAKERS:url-prefix'));
    }

    if (url.includes('paattajat')) {
      url = url.toString().replace('paattajat', t('POLICYMAKERS:url-prefix'));
    }
    else if (url.includes('beslutsfattare')) {
      url = url.toString().replace('beslutsfattare', t('POLICYMAKERS:url-prefix'));
    }
    else if (url.includes('decisionmakers')) {
      url = url.toString().replace('decisionmakers', t('POLICYMAKERS:url-prefix'));
    }

  }

  return (
    <article className='policymaker-search-result-card'>
      <a
        href={url}
        data-color-class={colorClass}
        className='policymaker-search-result-card__container'
      >
        <span className='policymaker-search-result-card__department-highlight' style={{backgroundColor: colorClass}}></span>
        <div className='policymaker-search-result-card__title-container'>
          <h2 className='policymaker-search-result-card__title'>{trustee_name ?? title}</h2>
          {
            trustee_title &&
            <div className='policymaker-search-result-card__sub-title'>{ translatedTrusteeTitle(trustee_title) }</div>
          }
          {
            organization_hierarchy &&
            <div className='policymaker-search-result-card__sub-title'>{ formattedOrganizations(organization_hierarchy) }</div>
          }
        </div>
        <IconArrowRight size='m' />
      </a>
    </article>
  )
}

export default ResultCard;
