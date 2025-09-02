import './i18n';
import React, { createContext } from 'react';
import ReactDOM from 'react-dom';
import DecisionsContainer from './modules/decisions/SearchContainer';
import PolicymakersContainer from './modules/policymakers/SearchContainer';
import FrontpageContainer from './modules/frontpage/SearchContainer';
import initSentry from './common/Sentry';

// Determine which data source we use once policymakers search is implemented
const rootElement = document.getElementById('paatokset_search');
let searchContainer;

// Set to true for some additional info.
window._DEBUG_MODE_ = false;
// Need to instantiate this or reactivesearch dies.
window.process = { env: {} };

initSentry();

export const OperatorGuideContext = createContext(rootElement?.dataset.operatorGuideUrl || '');

if(rootElement) {
  const type = rootElement.dataset.type || 'decisions';
  const elasticUrl = rootElement.dataset.url || 'http://localhost:9200';

  switch(type) {
    case 'decisions':
      searchContainer = <DecisionsContainer url={elasticUrl} />;
      break;
    case 'policymakers':
      searchContainer = <PolicymakersContainer url={elasticUrl} />;
      break;
    case 'frontpage':
      searchContainer = <FrontpageContainer url={elasticUrl} />;
      break;
    default:
      searchContainer = null;
  }
}

ReactDOM.render(
  <React.StrictMode>
    <section>
      {searchContainer}
    </section>
  </React.StrictMode>,
  document.getElementById('paatokset_search')
);
