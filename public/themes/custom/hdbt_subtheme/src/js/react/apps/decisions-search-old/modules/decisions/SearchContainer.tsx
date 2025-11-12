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
    backgroundColor: 'var(--color-silver-light)'
  }
};

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
      app={`${Indices.PAATOKSET_DECISIONS},${Indices.PAATOKSET_POLICYMAKERS}`}
      theme={baseTheme}
      url={url}
      >
        <FormContainer
          langcode={t('SEARCH:langcode')}
          formDescription={drupalSettings?.paatokset_search?.default_texts?.description}
          searchTriggered={searchTriggered}
          triggerSearch={triggerSearch}
        />
        <ResultsContainer />
      </ReactiveBase>
  );
};

export default SearchContainer;
