import CardItem from '@/react/common/Card';
import { matchTypeLabel } from '../helpers';
import type { Decision } from '../types/Decision';

const getShortAddress = (address: string) => {
  const regex = /(?:^|(?:[.!?]\s))(\w+)/;
  const matches = address.match(regex);

  if (matches?.length) {
    return matches[0];
  }

  return null;
};

export const ResultCard = ({ address, approval_type, document_created, document_type, label, url }: Decision) => {
  const getCardTitle = () => {
    let result = matchTypeLabel(document_type[0]);

    if (address?.length) {
      const shortAddress = getShortAddress(address[0]);
      result = shortAddress === null || shortAddress === 'null' ? result : `${result}, ${shortAddress}`;
    }

    return `${result}, ${Drupal.t('identifier', {}, { context: 'Allu decision search' })} ${label[0]} (pdf)`;
  };

  const getTime = () => {
    if (document_created?.length) {
      const date = new Date(document_created[0] * 1000);
      return date.toLocaleString('fi-FI', { day: 'numeric', month: 'numeric', year: 'numeric' });
    }
  };

  const getFullAddress = () => {
    if (!address?.[0]) {
      return '';
    }

    if (address[0].length > 165) {
      return `${address[0].slice(0, 165)}...`;
    }

    return address[0];
  };

  return (
    <CardItem
      cardModifierClass='card--border'
      cardTags={
        approval_type?.includes('WORK_FINISHED')
          ? [{ tag: Drupal.t('Work complete', {}, { context: 'Allu decision search' }) }]
          : []
      }
      cardTitle={getCardTitle()}
      cardUrl={url?.[0] ?? ''}
      location={getFullAddress()}
      locationLabel={Drupal.t('Address', {}, { context: 'Allu decision search' })}
      time={getTime()}
      timeLabel={Drupal.t('Date of decision', {}, { context: 'Allu decision search' })}
    />
  );
};
