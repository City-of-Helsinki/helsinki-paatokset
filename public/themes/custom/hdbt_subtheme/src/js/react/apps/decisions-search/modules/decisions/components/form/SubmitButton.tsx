import React from 'react';
import { useTranslation } from 'react-i18next';
import { ReactiveComponent } from '@appbaseio/reactivesearch';
import { Button } from 'hds-react';
import classNames from 'classnames';

import useWindowDimensions from '../../../../hooks/useWindowDimensions';

type Props = {
  disabled: boolean
}

const SubmitButton = ({ disabled }: Props) => {
  const { width } = useWindowDimensions();
  const { t } = useTranslation();
  const customClass= width > 1248 ?
    'decisions-search-submit-button--desktop' :
    'decisions-search-submit-button--mobile';

  return (
    <ReactiveComponent
      componentId='submit-button'
      render={() => (
        <Button
          type='submit'
          className={classNames(
            'decisions-search-form-element',
            'decisions-search-submit-button',
            customClass
          )}
          disabled={disabled}
          style={{
            borderColor: 'black'
          }}
        >
          {t('SEARCH:submit')}
        </Button>
      )}
    />
  );
}

export default SubmitButton;
