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
};

type Props = {
  url: string
};

const SearchContainer = ({ url }: Props) => {
  const { t } = useTranslation();
  const [searchTriggered, setSearchStatus] = useState<boolean>(false);
  const [lastRefreshTime, setLastRefreshTime] = useState<string>('');

  const triggerSearch = () => {
    setSearchStatus(true);
  };

  const setLastRefreshed = () => {
    setLastRefreshTime(String(Date.now()));
  };

  const getLastRefreshed = () => lastRefreshTime;

  return (
    <ReactiveBase
      url={url}
      app={Indices.PAATOKSET_POLICYMAKERS}
      theme={baseTheme}
      >
        <FormContainer
          langcode={t('SEARCH:langcode')}
          searchTriggered={searchTriggered}
          triggerSearch={triggerSearch}
          setLastRefreshed={setLastRefreshed} />
        {searchTriggered &&
          <ResultsContainer
            getLastRefreshed={getLastRefreshed} />
        }
      </ReactiveBase>
  );
};

export default SearchContainer;
