import React, { useState } from 'react';
import { ReactiveBase } from '@appbaseio/reactivesearch';
import { useTranslation } from 'react-i18next';

import Indices from '../../Indices';
import FormContainer from './components/form/FormContainer';
import ResultsContainer from './components/results/ResultsContainer';

const baseTheme = {
  typography: {
    fontFamily: 'var(--font-default)'
  },
  colors: {
    backgroundColor: '#f7f7f8'
  }
}

type Props = {
  url: string
};

const SearchContainer = ({ url }: Props) => {
  const { t } = useTranslation();
  const [searchTriggered, setSearchState] = useState<boolean>(false);
  const triggerSearch = () => {
    setSearchState(true);
  };

  return (
    <ReactiveBase
      url={url}
      app={`${Indices.PAATOKSET_DECISIONS},${Indices.PAATOKSET_POLICYMAKERS}`}
      theme={baseTheme}
      >
        <FormContainer
          langcode={t('SEARCH:langcode')}
          formDescription={drupalSettings?.paatokset_search?.default_texts?.description}
          searchTriggered={searchTriggered}
          triggerSearch={triggerSearch}
        />
        <ResultsContainer />
      </ReactiveBase>
  )
}

export default SearchContainer;
