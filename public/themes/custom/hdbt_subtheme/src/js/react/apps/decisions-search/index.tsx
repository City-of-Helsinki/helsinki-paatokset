import './i18n';
import React, { createContext } from 'react';
import ReactDOM from 'react-dom';
import DecisionsContainer from './modules/decisions/SearchContainer';
import PolicymakersContainer from './modules/policymakers/SearchContainer';
import FrontpageContainer from './modules/frontpage/SearchContainer';
import * as serviceWorker from './serviceWorker';
import initSentry from "./common/Sentry";

// Global styles
import './index.scss';

// Determine which data source we use once policymakers search is implemented
const rootElement = document.getElementById('paatokset_search');
let searchContainer;

initSentry()

export const OperatorGuideContext = createContext(rootElement?.dataset.operatorGuideUrl || process.env.REACT_APP_OPERATOR_GUIDE_URL || '');

if(rootElement) {
  const type = rootElement.dataset.type;
  const fallbackUrl = process.env.REACT_APP_ELASTIC_URL || 'http://localhost:9200';
  const elasticUrl = rootElement.dataset.url || fallbackUrl;

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

// If you want your app to work offline and load faster, you can change
// unregister() to register() below. Note this comes with some pitfalls.
// Learn more about service workers: https://bit.ly/CRA-PWA
serviceWorker.unregister();
