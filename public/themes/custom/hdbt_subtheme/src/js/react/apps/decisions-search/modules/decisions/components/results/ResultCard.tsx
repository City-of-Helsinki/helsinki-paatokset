import React from 'react';
import { IconArrowRight } from 'hds-react';
import { format } from 'date-fns';

import classNames from 'classnames';
import useDepartmentClasses from '../../../../hooks/useDepartmentClasses';


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
};

const ResultCard = ({ category, color_class, date, href, lang_prefix, url_prefix, url_query, amount_label, issue_id, unique_issue_id, doc_count, organization_name, subject, issue_subject, _score }: Props) => {
  const colorClass = useDepartmentClasses(color_class);

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
  if(date) {
    formattedDate = format(new Date(date * 1000), 'dd.MM.yyyy');
  }

  let cardClass = 'decisions-search-result-card';
  if (doc_count > 1) {
    cardClass += ' decisions-search-multiple-results';
  }

  return (
    <div className={cardClass}>
      <a href={ url } className='decisions-search-result-card__link' tabIndex={0}>
        <div className='decisions-search-multiple-results__label' style={{ backgroundColor: colorClass }}>
          { organization_name }
        </div>
        <div className='decisions-search-result-card__container'>
          <div>
            <div className='decisions-search-result-card__date'>
              { formattedDate }
            </div>
          </div>
          <div className='decisions-search-result-card__title'>
            {_DEBUG_MODE_ &&
              <span style={{ color: 'red' }}>Score: { _score }, Diary number: { issue_id }, Unique issue ID: { unique_issue_id }, Doc Count: { doc_count } <br /> URL: { href }</span>
            }
            <h2>{ subject }</h2>
            {
              doc_count > 1 && issue_subject &&
                <div className='decisions-search-result-card__amount'>
                  <p><strong>{amount_label}</strong>
                  <br />{ issue_subject }</p>
                </div>
            }
          </div>
        </div>
        <div className='decisions-search-result-card__footer'>
          {
            category &&
              <div className={classNames(
                'decisions-search-result-card__tags',
                'paatokset-tag-container'
              )}>
                <span className='decisions-search-result-card__search-tag'>{ category }</span>
              </div>
          }
          <div className='decisions-search-result-card__issue-link'>
              <IconArrowRight size="l"/>
          </div>
        </div>
      </a>
    </div>
  );
};

export default ResultCard;
