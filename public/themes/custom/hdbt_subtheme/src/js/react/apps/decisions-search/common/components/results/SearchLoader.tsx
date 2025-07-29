import React from 'react';
import { useTranslation } from 'react-i18next';
import style from './SearchLoader.module.scss';

const FormTitle = () => {
  const { t } = useTranslation();

  return (
    <div className={style.SearchLoader}>
      <p>{t('SEARCH:loading')}</p>
    </div>
  );
}

export default FormTitle;
