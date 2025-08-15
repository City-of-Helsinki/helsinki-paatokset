import { ReactiveBase } from '@appbaseio/reactivesearch';
import { useTranslation } from 'react-i18next';
import Indices from '../../Indices';
import FormContainer from './components/form/FormContainer';

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

  return (
    <ReactiveBase
      url={url}
      app={Indices.PAATOKSET_DECISIONS}
      theme={baseTheme}
      >
        <FormContainer
          langcode={t('SEARCH:langcode')}
          searchTriggered
          triggerSearch={function(){}}
          searchLabel={t('DECISIONS:frontpage-search-label')}
          searchRedirect={t('DECISIONS:redirect-uri')}
        />
      </ReactiveBase>
  );
};

export default SearchContainer;
