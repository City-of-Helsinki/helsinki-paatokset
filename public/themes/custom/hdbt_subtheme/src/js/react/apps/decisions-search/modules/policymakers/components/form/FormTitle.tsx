import { useTranslation } from 'react-i18next';

const FormTitle = () => {
  const { t } = useTranslation();

  return (
    <h2>{t('POLICYMAKERS:form-title')}</h2>
  )
}

export default FormTitle;
