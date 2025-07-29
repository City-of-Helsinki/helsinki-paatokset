import Combobox from '../../../../../common/components/form/Combobox';
import { useTranslation } from 'react-i18next';

import IndexFields from '../../../enum/IndexFields';

type Props = {
  aggregations: any,
  value: string[],
  setValue: Function,
  setQuery: Function,
  queryValue: Array<string>
}

const OrganSelect = ({ aggregations, value, setValue, setQuery, queryValue }: Props) => {
  const { t } = useTranslation();

  return (
    <Combobox
      aggregations={aggregations}
      aggregationKey={IndexFields.ORGAN}
      value={value}
      setValue={setValue}
      setQuery={setQuery}
      queryValue={queryValue}
      label={t('POLICYMAKERS:organ')}
      placeholder={t('POLICYMAKERS:choose-organ')}
    />
  );
};

export default OrganSelect;