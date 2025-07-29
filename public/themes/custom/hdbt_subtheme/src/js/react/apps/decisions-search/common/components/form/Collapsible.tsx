import React, { useRef, useState } from 'react';
import { IconAngleDown, IconAngleUp } from 'hds-react';

import '../../../hooks/useOutsideClick';
import './Collapsible.scss';
import useOutsideClick from '../../../hooks/useOutsideClick';

type Props = {
  active?: boolean,
  title: string|React.ReactNode,
  children: React.ReactElement,
  showHandle?: boolean,
  ariaControls?: string,
}

const Collapsible = ({active, ariaControls, title, children, showHandle}: Props) => {
  const [isActive, setActive] = useState<boolean>(active||false);
  const ref = useRef<HTMLDivElement|null>(null);

  const getHandle = () => {
    if(showHandle !== false) {
      return isActive ? 
        <IconAngleUp /> :
        <IconAngleDown />;
    }
  }

  useOutsideClick(ref, () => {
    setActive(false);
  });

  return (
    <div className='Collapsible collapsible-wrapper'>
      <button
        className='Collapsible__control Collapsible__element'
        aria-controls={ariaControls}
        aria-expanded={isActive}
        onClick={() => setActive(!isActive)}
      >
        <span className='Collapsible__title'>{ title }</span>
        {getHandle()}
      </button>
      {isActive &&
        <div className='Collapsible__element Collapsible__element--children'>
          {children}
        </div>
      }
    </div>
  );
}; 

export default Collapsible;
