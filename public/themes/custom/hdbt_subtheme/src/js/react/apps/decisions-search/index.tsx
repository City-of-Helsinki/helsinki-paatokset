import React from 'react';
import ReactDOM from 'react-dom';

import initSentry from '../decisions-search-old/common/Sentry';
import { DecisionsContainer } from './modules/decisions/DecisionsContainer';

initSentry(); 

const ROOT_ID = 'paatokset_search';

document.addEventListener('DOMContentLoaded', () => {
  const rootElement = document.getElementById(ROOT_ID);

  if (!rootElement) {
    throw new Error('Root id missing for decisions search app');
  }

  const type = rootElement.dataset.type || 'decisions';
  const elasticUrl = rootElement.dataset.url || 'http://localhost:9200';

  switch(type) {
    case 'decisions':
      searchContainer = <DecisionsContainer url={elasticUrl} />;
      break;
    // case 'policymakers':
    //   searchContainer = <PolicymakersContainer url={elasticUrl} />;
    //   break;
    // case 'frontpage':
    //   searchContainer = <FrontpageContainer url={elasticUrl} />;
    //   break;
    default:
      searchContainer = <DecisionsContainer url={elasticUrl} />;
  }

  ReactDOM.render(    
    <React.StrictMode>
      {searchContainer}
    </React.StrictMode>,
    rootElement
  );
});
