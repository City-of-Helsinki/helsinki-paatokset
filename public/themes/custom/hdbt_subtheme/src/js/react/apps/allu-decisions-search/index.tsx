import { ErrorBoundary } from '@sentry/react';
import React from 'react';
import { createRoot } from 'react-dom/client';
import ResultsError from '@/react/common/ResultsError';
import { SearchContainer } from './containers/SearchContainer';

const rootSelector = 'allu-decisions-search';
const rootElement = document.getElementById(rootSelector);

if (rootElement) {
  createRoot(rootElement).render(
    <React.StrictMode>
      <ErrorBoundary fallback={<ResultsError error='Allu decisions search crashed' />}>
        <SearchContainer />
      </ErrorBoundary>
    </React.StrictMode>,
  );
}
