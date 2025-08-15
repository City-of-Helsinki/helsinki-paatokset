import React, { Component, useContext } from 'react';
import { DataSearch } from '@appbaseio/reactivesearch';
import { useTranslation } from 'react-i18next';

import SearchBarWrapper from '../../../../common/components/form/SearchBarWrapper';
import SearchBarAutocomplete from '../../../../common/components/form/SearchBarAutocomplete';
import IndexFields from '../../enum/IndexFields';
import SearchComponents from '../../enum/SearchComponents';
import { isOperatorSearch } from '../../../../utils/OperatorSearch';
import { OperatorGuideContext } from '../../../../index';

const SearchBar = React.forwardRef<Component<DataSearchProps, any, any>, {value: string|undefined, setValue: any, URLParams: any, searchLabel: string|undefined, triggerSearch: any}>((props, ref) => {
  const { value, setValue, URLParams, searchLabel, triggerSearch } = props;
  const { t } = useTranslation();
  const query = (value: string) => {
    const dataFields = [
      `${IndexFields.SUBJECT}^100`,
      `${IndexFields.ISSUE_SUBJECT}^50`,
      `${IndexFields.DECISION_CONTENT}^10`,
      `${IndexFields.DECISION_MOTION}^1`,
      `${IndexFields.ISSUE_ID}^100`,
    ];
    return {
      bool: {
        // Add simple query string filter if the value contains an operator.
        ...((value && isOperatorSearch(value)) ? {
          filter: {
            simple_query_string: {
              query: value,
              default_operator: 'or',
              analyze_wildcard: true,
            }
          } } : {}),
        should: [
          {
            multi_match: {
              query: value,
              fields: dataFields,
              type: 'best_fields',
              operator: 'or',
              fuzziness: 0
            }
          },
          {
            multi_match: {
              query: value,
              fields: dataFields,
              type: 'phrase',
              operator: 'or'
            }
          },
          {
            multi_match: {
              query: value,
              fields: dataFields,
              type: 'phrase_prefix',
              operator: 'or'
            }
          }
        ],
      },
    };
  };

  const dataSearch = (
    <DataSearch
      ref={ref}
      componentId={SearchComponents.SEARCH_BAR}
      placeholder={t('DECISIONS:search-bar-placeholder')}
      autosuggest
      dataField={[
        IndexFields.SUBJECT,
        IndexFields.ISSUE_SUBJECT,
        IndexFields.DECISION_CONTENT,
        IndexFields.DECISION_MOTION,
        IndexFields.ISSUE_ID,
      ]}
      defaultQuery={(defaultValue) => ({
        query: query(defaultValue),
        size: 10,
        _source: {
          includes: ['*'],
          excludes: [] 
        },
      })}
      customQuery={(_value) => ({
          query: query(_value),
      })}
      value={value}
      onChange={setValue}
      onValueSelected={(selectedValue: any) => {
        setValue(selectedValue);
        triggerSearch(selectedValue);
      }}
      URLParams={URLParams}
      render={({ data, downshiftProps: { isOpen, getItemProps, highlightedIndex, selectedItem } }) => {
        const uniqueSuggestions:string[] = [];
        const parsedData = [];
        for (let i = 0; i < data.length; i++) {
          const subject: string = data[i].source[IndexFields.SUBJECT][0];
          if (uniqueSuggestions.includes(subject)) {
            continue;
          }

          if (
            typeof data[i].source[IndexFields.HAS_TRANSLATION] !== 'undefined' &&
            data[i].source[IndexFields.HAS_TRANSLATION][0] === true &&
            data[i].source[IndexFields.LANGUAGE].toString() !== t('SEARCH:langcode')
          ) {
            continue;
          }

          uniqueSuggestions.push(subject);
          parsedData.push({
            label: subject,
            value: subject
          });
        }
        return isOpen && parsedData.length > 0 && (
          <SearchBarAutocomplete parsedData={parsedData} getItemProps={getItemProps} highlightedIndex={highlightedIndex} selectedItem={selectedItem} />
        );
      }}
    />
  );

  const label = searchLabel || t('DECISIONS:search-bar-label');
  const operatorGuideUrl = useContext(OperatorGuideContext);
  const status = {
    label: t('SEARCH:operators-enabled-label'),
    messageVisible: (value && isOperatorSearch(value) && (
      <>
        {t('SEARCH:operators-enabled')} {operatorGuideUrl && (
          <>
            <a href={operatorGuideUrl} target="_blank" rel="noopener noreferrer">{t('SEARCH:operators-enabled-read-more')}</a>.
          </>
        )}
      </>
    )) || '',
    messageAnnounced: t('SEARCH:operators-enabled')
  };

  return (
    <SearchBarWrapper
      label={label}
      dataSearch={dataSearch}
      status={status}
    />
  );
});

export default SearchBar;
