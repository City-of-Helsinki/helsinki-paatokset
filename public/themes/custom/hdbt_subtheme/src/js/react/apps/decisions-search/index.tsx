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

// eslint-disable-next-line
window._DEBUG_MODE_ = false;
window.process = { env: {} };


initSentry();

export const OperatorGuideContext = createContext(rootElement?.dataset.operatorGuideUrl || '');

if(rootElement) {
  const type = rootElement.dataset.type || 'decisions';
  // https://paatokset-search.api.hel.fi
  const elasticUrl = 'https://paatokset-search.api.hel.fi' || rootElement.dataset.url || 'http://localhost:9200';

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
