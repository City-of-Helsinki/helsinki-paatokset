import { ReactiveComponent } from '@appbaseio/reactivesearch';
import { Button } from 'hds-react';
import { useTranslation } from 'react-i18next';
import useWindowDimensions from '../../../../hooks/useWindowDimensions';
import classNames from 'classnames';

import SearchComponents from '../../enum/SearchComponents';

import formStyles from '../../../../common/styles/Form.module.scss';
import styles from './SubmitButton.module.scss';

const SubmitButton = () => {
  const { t } = useTranslation();
  const { width } = useWindowDimensions();
  const customClass = width >= 1248 ? styles.SubmitButton__desktop : styles.SubmitButton__mobile;

  return (
    <ReactiveComponent
      componentId={SearchComponents.SUBMIT_BUTTON}
      render={() => (
        <Button
          type='submit'
          tabIndex={0}
          className={classNames(
            styles.SubmitButton,
            formStyles['form-element'],
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
