import React from 'react';
import ReactDOM from 'react-dom';
import { ErrorBoundary } from '@sentry/react';

import { SearchContainer } from './containers/SearchContainer';
import ResultsError from '@/react/common/ResultsError';

const rootSelector = 'allu-decisions-search';
const rootElement = document.getElementById(rootSelector);

if (rootElement) {
  ReactDOM.render(
    <React.StrictMode>
      <ErrorBoundary fallback={<ResultsError error='Allu decisions search crashed' />}>
        <SearchContainer />
      </ErrorBoundary>
    </React.StrictMode>,
    rootElement
  );
}
