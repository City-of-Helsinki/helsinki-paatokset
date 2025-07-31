import React, { Component } from 'react';
import { SearchBox } from '@appbaseio/reactivesearch';
import { useTranslation } from 'react-i18next';

import SearchBarWrapper from '../../../../common/components/form/SearchBarWrapper';
import SearchBarAutocomplete from '../../../../common/components/form/SearchBarAutocomplete';
import IndexFields from '../../enum/IndexFields';
import SearchComponents from '../../enum/SearchComponents';

const SearchBar = React.forwardRef<Component<DataSearchProps, any, any>, {value: string, setValue: any}>((props, ref) => {
  const { value, setValue} = props;
  const { t } = useTranslation();

  const dataSearch = (
    null
    // <DataSearch
    //   ref={ref}
    //   componentId={SearchComponents.SEARCH_BAR}
    //   dataField={[
    //     IndexFields.TITLE,
    //     IndexFields.COMBINED_TITLE,
    //     IndexFields.TRUSTEE_NAME,
    //     IndexFields.DM_FIRST_NAME,
    //     IndexFields.DM_LAST_NAME
    //   ]}
    //   placeholder={t('POLICYMAKERS:search-bar-placeholder')}
    //   autosuggest={true}
    //   value={value}
    //   defaultValue={value}
    //   onValueSelected={function(value:any) {
    //     //...
    //   }}
    //   onChange={setValue}
    //   URLParams={true}
    //   render={function ({data, downshiftProps: { isOpen, getItemProps, highlightedIndex, selectedItem }}) {
    //     const parsedData = [];
    //     for (let i = 0; i < data.length; i++) {
    //       let subject = data[i].value;
    //       if (
    //         typeof data[i].source[IndexFields.TRUSTEE_NAME] === 'undefined' &&
    //         data[i].source[IndexFields.HAS_TRANSLATION][0] === true &&
    //         data[i].source[IndexFields.LANGUAGE].toString() !== t('SEARCH:langcode')
    //       ) {
    //         continue;
    //       }

    //       if (data[i].source[IndexFields.POLICYMAKER_EXISTING] === undefined || data[i].source[IndexFields.POLICYMAKER_EXISTING][0] === false) {
    //         continue;
    //       }

    //       // Always show combined title if one exists.
    //       if (data[i].source[IndexFields.COMBINED_TITLE] && data[i].source[IndexFields.COMBINED_TITLE][0]) {
    //         subject = data[i].source[IndexFields.COMBINED_TITLE][0];
    //       }

    //       parsedData.push({
    //         label: subject,
    //         value: subject
    //       });
    //     }
    //     return isOpen && parsedData.length > 0 && (
    //       <SearchBarAutocomplete parsedData={parsedData} getItemProps={getItemProps} highlightedIndex={highlightedIndex} selectedItem={selectedItem} />
    //     );
    //   }}

    // />
  );

  const label = t('POLICYMAKERS:search-bar-label');

  return (
    <SearchBarWrapper
      label={label}
      dataSearch={dataSearch}
    />
  );
});

export default SearchBar;
