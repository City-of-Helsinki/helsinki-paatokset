import React from 'react';
import { useTranslation } from 'react-i18next';
import { ReactiveComponent } from '@appbaseio/reactivesearch';
import { Button } from 'hds-react';
import classNames from 'classnames';

import useWindowDimensions from '../../../../hooks/useWindowDimensions';
import formStyles from '../../../../common/styles/Form.module.scss';
import styles from './SubmitButton.module.scss';

type Props = {
  disabled: boolean
}

const SubmitButton = ({ disabled }: Props) => {
  const { width } = useWindowDimensions();
  const { t } = useTranslation();
  const customClass= width > 1248 ? styles.SubmitButton__desktop : styles.SubmitButton__mobile;

  return (
    <ReactiveComponent
      componentId={'submit-button'}
      render={() => (
        <Button
          type='submit'
          className={classNames(
            formStyles['form-element'],
            styles.SubmitButton,
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
