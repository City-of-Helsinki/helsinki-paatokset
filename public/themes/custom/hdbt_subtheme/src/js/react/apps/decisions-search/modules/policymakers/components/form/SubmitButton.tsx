import { ReactiveComponent } from '@appbaseio/reactivesearch';
import { Button } from 'hds-react';
import { useTranslation } from 'react-i18next';
import useWindowDimensions from '../../../../hooks/useWindowDimensions';
import classNames from 'classnames';

import SearchComponents from '../../enum/SearchComponents';

const SubmitButton = () => {
  const { t } = useTranslation();
  const { width } = useWindowDimensions();
  const customClass = width >= 1248 ? 'decisions-search-submit-button--desktop' : 'decisions-search-submit-button--mobile';

  return (
    <ReactiveComponent
      componentId={SearchComponents.SUBMIT_BUTTON}
      render={() => (
        <Button
          type='submit'
          tabIndex={0}
          className={classNames(
            'decisions-search-submit-button',
            'decisions-search-submit-button--policymakers',
            'decisions-search-form-element',
            customClass
          )}
        >
          {t('SEARCH:submit')}
        </Button>
      )}
    />
  )
}

export default SubmitButton;
