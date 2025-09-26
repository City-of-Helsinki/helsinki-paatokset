import { Select, SelectData, useSelectStorage } from 'hds-react';
import { type estypes } from '@elastic/elasticsearch';
import { useAtomCallback } from 'jotai/utils';
import { useCallback, useEffect } from 'react';
import { useSetAtom } from 'jotai';

import { clearAllSelectionsFromStorage, updateSelectionsInStorage } from '@/react/common/helpers/HDS';
import { Components } from '../enum/Components';
import { defaultMultiSelectTheme } from '@/react/common/constants/selectTheme';
import { PolicyMaker } from '../../../common/types/PolicyMaker';
import { getDecisionMakersAtom, setDecisionMakersAtom } from '../store';
import { Events } from '../enum/Events';
import { PolicymakerIndex } from '../enum/IndexFields';

const dataFields = [
  'title',
  'trustee_name',
  'field_last_name',
  'field_first_name',
];

const getQuery = (searchTerm): estypes.QueryDslQueryContainer => ({
  query: {
    bool: {
      should: [
        {
          multi_match: {
            query: searchTerm,
            fields: dataFields,
            type: 'best_fields',
            operator: 'or',
            fuzziness: 0
          }
        },
        {
          multi_match: {
            query: searchTerm,
            fields: dataFields,
            type: 'phrase',
            operator: 'or'
          }
        },
        {
          multi_match: {
            query: searchTerm,
            fields: dataFields,
            type: 'phrase_prefix',
            operator: 'or'
          }
        },
      ],
      minimum_should_match: 1,
    },
  }
});

export const DMSelect = ({
  url,
}: {
  url: string;
}) => {
  const setDecisionMakers = useSetAtom(setDecisionMakersAtom);
  const getDMSelectValue = useAtomCallback(
    useCallback((get) => get(getDecisionMakersAtom)),
  );

  const onChange = (selectedOptions: Array<{label: string, value: string}>) => {
    setDecisionMakers(selectedOptions);
    selectStorage.updateAllOptions((option, group, groupindex) => ({
      ...option,
      selected: selectedOptions.some(selection => selection.value === option.value),
    }));
  };

  const getDecisionMakers = async(
    searchTerm: string,
    selectedOptions: Array<{label: string, value: string}>,
    data: SelectData
  ) => {
    const response = await fetch(`${url}/paatokset_policymakers/_search`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
      },
      body: JSON.stringify({
        ...getQuery(searchTerm),
      }),
    });
    
    const json = await response.json();
    const result = [];
    
    if (json?.hits?.hits) {
      result.options = json.hits.hits
        .filter((hit: estypes.SearchHit<PolicyMaker>) => hit._source.field_policymaker_id?.[0])
        .map(hit => ({
          value: hit._source[PolicymakerIndex.FIELD_POLICYMAKER_ID].toString(),
          label: hit._source[PolicymakerIndex.DECISIONMAKER_COMBINED_TITLE].toString(),
        })
      );
    }
    
    return result;
  };

  const selectStorage = useSelectStorage({
    disabled: false,
    id: Components.DECISIONMAKER,
    invalid: false,
    multiSelect: true,
    noTags: true,
    onChange,
    onSearch: getDecisionMakers,
    open: false,
    options: getDMSelectValue().map(dm => ({
      ...dm,
      selected: true,
    })),
  });

  const clearAllSelections = () => {
    clearAllSelectionsFromStorage(selectStorage);
  };

  const updateSelections = () => {
    updateSelectionsInStorage(selectStorage, getDMSelectValue());
  };

  useEffect(() => {
    window.addEventListener(Events.DECISIONS_CLEAR_ALL, clearAllSelections);
    window.addEventListener(Events.DECISIONS_CLEAR_SINGLE_DM, updateSelections);

    return () => {
      window.removeEventListener(Events.DECISIONS_CLEAR_ALL, clearAllSelections);
      window.removeEventListener(Events.DECISIONS_CLEAR_SINGLE_DM, updateSelections);
    };
  });

  return (
    <Select
      className='hdbt-search__dropdown'
      texts={{
        label: Drupal.t('Decision-maker / Division', {}, {context: 'Decisions search'}),
        placeholder: Drupal.t('All decision-makers and divisions', {}, {context: 'Decisions search'}),
      }}
      theme={defaultMultiSelectTheme}
      {...selectStorage.getProps()}
    />
  );
};
