import { useTranslation } from 'react-i18next';
import Combobox from '../../../../../common/components/form/Combobox';

import IndexFields from '../../../enum/IndexFields';

type Props = {
  aggregations: any,
  value: string[],
  setValue: Function,
  setQuery: Function,
  queryValue: Array<string>,
}

const SectorSelect = ({ aggregations, value, setValue, setQuery, queryValue }: Props) => {
  const { t } = useTranslation();

  return (
    <Combobox
      aggregations={aggregations}
      aggregationKey={IndexFields.SECTOR}
      value={value}
      setValue={setValue}
      setQuery={setQuery}
      queryValue={queryValue}
      label={t('POLICYMAKERS:field')}
      placeholder={t('POLICYMAKERS:choose-field')}
    />
  );
};

export default SectorSelect;