import { ErrorBoundary } from '@sentry/react';
import React, { Suspense } from 'react';
import ReactDOM from 'react-dom';
import { GhostList } from '@/react/common/GhostList';
import ResultsError from '@/react/common/ResultsError';
import initSentry from '@/react/common/helpers/Sentry';
import { DecisionsContainer } from './modules/decisions/DecisionsContainer';
import { PolicymakerContainer } from './modules/policymakers/PolicymakerContainer';

initSentry();

const ROOT_ID = 'paatokset_search';

document.addEventListener('DOMContentLoaded', () => {
  const rootElement = document.getElementById(ROOT_ID);

  if (!rootElement) {
    throw new Error('Root id missing for decisions search app');
  }

  const type = rootElement.dataset.type || 'decisions';
  const elasticUrl = rootElement.dataset.url || 'http://localhost:9200';

  let searchContainer: React.ReactElement;

  switch (type) {
    case 'decisions':
      searchContainer = <DecisionsContainer url={elasticUrl} />;
      break;
    case 'policymakers':
      searchContainer = <PolicymakerContainer />;
      break;
    // case 'frontpage':
    //   searchContainer = <FrontpageContainer url={elasticUrl} />;
    //   break;
    default:
      searchContainer = <DecisionsContainer url={elasticUrl} />;
  }

  ReactDOM.render(
    <React.StrictMode>
      <ErrorBoundary fallback={<ResultsError />}>
        <Suspense fallback={<GhostList count={10} />}>{searchContainer}</Suspense>
      </ErrorBoundary>
    </React.StrictMode>,
    rootElement,
  );
});
