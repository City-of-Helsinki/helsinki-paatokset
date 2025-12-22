import CardItem from '@/react/common/Card';
import { matchTypeLabel } from '../helpers';
import type { Decision } from '../types/Decision';

export const ResultCard = ({ address, approval_type, document_created, document_type, label, url }: Decision) => {
  const getCardTitle = () => {
    const result = matchTypeLabel(document_type[0]);
    return `${result}, ${Drupal.t('identifier', {}, { context: 'Allu decision search' })} ${label[0]} (pdf)`;
  };

  const getTime = () => {
    if (document_created?.length) {
      const date = new Date(document_created[0] * 1000);
      return date.toLocaleString('fi-FI', { day: 'numeric', month: 'numeric', year: 'numeric' });
    }
  };

  const getFullAddress = () => {
    // Make sure that the address is not displayed if it is null, 'null' or undefined.
    if (!address?.[0] || address?.[0] === 'null' || address?.[0] === null) {
      return '';
    }

    // Don't let the addresses be too long.
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
