import React from 'react';
import { IconSearch, Notification } from 'hds-react';

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

const SearchBarWrapper = ({ label, dataSearch, status = null }: Props) => {
  const searchElement = React.cloneElement(
    dataSearch,
    {
      iconPosition: 'right',
      icon: <IconSearch />,
      className: classNames(
        'decisions-search-bar-wrapper__input',
        'decisions-search-form-element',
      )
    }
  );

  return (
    <div className='decisions-search-bar-wrapper'>
      <div className='decisions-search-bar-wrapper__label'>
        <span>{label}</span>
        {status && (
          <div className='decisions-search-bar-wrapper__label-status'>
            <span>{status.messageVisible}</span>
            <Notification
              label={status.label}
              notificationAriaLabel={status.label}
              invisible
            >
              {status.messageAnnounced}
            </Notification>
          </div>
        )}
      </div>
      {searchElement}
    </div>
  );
};

export default SearchBarWrapper;
