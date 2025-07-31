import React from 'react';
import { useTranslation } from 'react-i18next';

const FormTitle = () => {
  const { t } = useTranslation();

  return (
    <div className='decisions-search-loader'>
      <p>{t('SEARCH:loading')}</p>
    </div>
  );
}

export default FormTitle;
