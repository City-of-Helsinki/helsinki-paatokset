import style from './ResultCard.module.scss';
import classNames from 'classnames';

const PhantomCard = () => {
  return <div className={classNames(style.ResultCard, style.PhantomCard)}></div>
}

export default PhantomCard;
