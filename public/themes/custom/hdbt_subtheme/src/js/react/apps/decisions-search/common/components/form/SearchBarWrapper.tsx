import React from 'react';
import { IconSearch, Notification } from 'hds-react';

import formStyles from '../../../common/styles/Form.module.scss';
import styles from './SearchBarWrapper.module.scss'
import classNames from 'classnames';

type Props = {
  label: string,
  dataSearch: JSX.Element,
  status?: null|{
    label: string,
    messageVisible: string|JSX.Element,
    messageAnnounced: string
  }
}

const SearchBarWrapper = ({label, dataSearch, status = null}: Props) => {
  const searchElement = React.cloneElement(
    dataSearch,
    {
      iconPosition: 'right',
      icon: <IconSearch />,
      className: classNames(
        styles.SearchBar__input,
        formStyles['form-element']
      )
    }
  );

  return (
    <div className={styles.SearchBarWrapper}>
      <div className={styles.SearchBarWrapper__label}>
        <label>{label}</label>
        {status && (
          <div className={styles.SearchBarWrapper__label__status}>
            <span>{status.messageVisible}</span>
            <Notification
              label={status.label}
              notificationAriaLabel={status.label}
              invisible={true}
            >
              {status.messageAnnounced}
            </Notification>
          </div>
        )}
      </div>
      {searchElement}
    </div>
  )
}

export default SearchBarWrapper;
