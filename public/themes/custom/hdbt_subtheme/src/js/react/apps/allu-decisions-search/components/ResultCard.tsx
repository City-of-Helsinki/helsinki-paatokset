import CardItem from '@/react/common/Card';
import { matchTypeLabel } from '../helpers';
import type { Decision } from '../types/Decision';

export const ResultCard = ({ address, approval_type, document_created, document_type, id, label, url }: Decision) => {
  const approvalType = approval_type?.toString();

  const getCardTitle = () => {
    const result = matchTypeLabel(document_type[0]);
    const title = `${result}, ${Drupal.t('identifier', {}, { context: 'Allu decision search' })} ${label[0]} (pdf)`;

    if (approvalType === 'WORK_FINISHED') {
      return `${title}, ${Drupal.t('Work complete', {}, { context: 'Allu decision search' }).toLowerCase()}`;
    }

    if (approvalType === 'OPERATIONAL_CONDITION') {
      return `${title}, ${Drupal.t('Operational condition', {}, { context: 'Allu decision search' }).toLowerCase()}`;
    }

    return title;
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

  const getUrl = () => {
    if (approvalType === 'WORK_FINISHED' || approvalType === 'OPERATIONAL_CONDITION') {
      const { currentLanguage } = drupalSettings.path;

      return `/${currentLanguage}/allu/document/${id}/approval/${approvalType}/download`;
    }

    return url?.[0] ?? '';
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
      cardUrl={getUrl()}
      location={getFullAddress()}
      locationLabel={Drupal.t('Address', {}, { context: 'Allu decision search' })}
      time={getTime()}
      timeLabel={Drupal.t('Date of decision', {}, { context: 'Allu decision search' })}
    />
  );
};
