import React from 'react';
import { IconArrowRight } from 'hds-react';
import { format } from 'date-fns';

import useDepartmentClasses from '../../../../hooks/useDepartmentClasses';

import style from './ResultCard.module.scss';
import classNames from 'classnames';

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

const ResultCard = ({category, color_class, date, href, lang_prefix, url_prefix, url_query, amount_label, issue_id, unique_issue_id, doc_count, organization_name, subject, issue_subject, _score}: Props) => {
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

  let cardClass = style.ResultCard;
  if (doc_count > 1) {
    cardClass += ' ' + style.MultipleResults;
  }

  return (
    <div className={cardClass}>
      <a href={ url } className={style.ResultCard__link} tabIndex={0}>
        <div className={style.ResultCard__label} style={{backgroundColor: colorClass}}>
          { organization_name }
        </div>
        <div className={style.ResultCard__container}>
          <div>
            <div className={style.ResultCard__date}>
              { formattedDate }
            </div>
          </div>
          <div className={style.ResultCard__title}>
            {process.env.REACT_APP_DEVELOPER_MODE &&
              <span style={{color: 'red'}}>Score: { _score }, Diary number: { issue_id }, Unique issue ID: { unique_issue_id }, Doc Count: { doc_count } <br /> URL: { href }</span>
            }
            <h2>{ subject }</h2>
            {
              doc_count > 1 && issue_subject &&
                <div className={style.ResultCard__amount}>
                  <p><strong>{amount_label}</strong>
                  <br />{ issue_subject }</p>
                </div>
            }
          </div>
        </div>
        <div className={style.ResultCard__footer}>
          {
            category &&
              <div className={classNames(
                style.ResultCard__tags,
                'paatokset-tag-container'
              )}>
                <span className={style['search-tag']}>{ category }</span>
              </div>
          }
          <div className={style['ResultCard__issue-link']}>
              <IconArrowRight size={'l'}/>
          </div>
        </div>
      </a>
    </div>
  );
}

export default ResultCard;
