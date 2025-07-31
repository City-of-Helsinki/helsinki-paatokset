import { Select } from 'hds-react';
import { Option, Options } from '../../../types/types';
import { useTranslation } from 'react-i18next';
import SpecialCases from '../../../enum/SpecialCases';

import { useEffect, useCallback, useState } from 'react';
import sectorMap, { SectorMap } from '../../../enum/SectorMap';

type Props = {
  setQuery: Function,
  setValues: Function,
  values: Options,
  opts: Options,
  queryValues: Options,
  langcode: string
};

const DecisionmakerSelect = ({setQuery, setValues, values, opts, queryValues, langcode}: Props) => {
  const { t } = useTranslation();
  const specialCases = [{
    label: t('DECISIONS:trustee'),
    sort_label: '0 - ' + t('DECISIONS:trustee'),
    value: SpecialCases.TRUSTEE,
    key: SpecialCases.TRUSTEE,
  }];

  const [selected, setSelected] = useState(queryValues);

  let sectors: Options = SectorMap.filter((sector) => {
    return sector.langcode === langcode;
  })
  .map((sector:any) => {
    return {
      label: t('SECTORS:' + sector.value),
      sort_label: t('SECTORS:' + sector.value),
      value: sector.value,
    };
  })
  .filter((sector)=>{ return sector });

  let options: any[] = [];
  options = sectors.concat(specialCases, opts);
  options.sort((a, b) => a.sort_label.localeCompare(b.sort_label));
  
  const triggerQuery = useCallback(() => {
    if(queryValues) {
      const specialCaseValues = [
        SpecialCases.TRUSTEE
      ];
      let finalQuery: any = {bool: {should: []}};
      let value: string|null = null;

      const values: string[] = [];
      queryValues.forEach((queryValue) => {

        const sector = sectorMap.find((item)=>{
          return item.value === queryValue.value;
        });

        if (sector) {
          finalQuery.bool.should.push({ term: { sector_id: queryValue.value }});
          value = queryValue.value;
          values.push(value);
        }
        else if(specialCaseValues.includes(queryValue.value)) {
          finalQuery.bool.should.push({ term: { special_status: queryValue.value }});
          value = queryValue.value;
          values.push(value);
        }
        else if(queryValues?.find((option: Option) => option.value === queryValue.value )) {
          finalQuery.bool.should.push({ term: { field_policymaker_id: queryValue.value }});
          value = queryValue.value;
          values.push(value);
        }
      });

      setQuery({
        query: finalQuery,
        value: values.toString()
      });
    }
    else {
      setQuery({
        query: null,
        values: null
      });
    }
  }, [queryValues, setQuery]);

  useEffect(() => {
    setSelected(queryValues);
    triggerQuery()
  }, [queryValues, setQuery, triggerQuery]);

  const onChange = (selected: any) => {
    setSelected(selected);
    if (selected.length) {
      setValues(selected);
    } else {
      setValues(null);
    }
  }

  return (
    <div className='decisions-search-form-element'>
      <Select
        multiSelect
        id="decisionmakerselect"
        value={selected}
        onChange={onChange}
        label={t('DECISIONS:decisionmaker')}
        placeholder={t('DECISIONS:choose-decisionmaker')}
        clearButtonAriaLabel='Clear all selections'
        selectedItemRemoveButtonAriaLabel={`Remove value`}
        toggleButtonAriaLabel={'Toggle'}
        theme={{
          '--focus-outline-color': 'var(--hdbt-color-black)',
          '--multiselect-checkbox-background-selected': 'black',
        }}
        options={options}
      />
    </div>
  );
}

export default DecisionmakerSelect;