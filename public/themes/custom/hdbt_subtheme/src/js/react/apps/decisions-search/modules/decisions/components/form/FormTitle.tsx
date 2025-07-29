import React from 'react';
import { useTranslation } from 'react-i18next';

const FormTitle = () => {
  const { t } = useTranslation();

  return (
    <div className='container container--search-title'>
      <h1>{t('DECISIONS:form-title')}</h1>
    </div>
  );
}

export default FormTitle;
