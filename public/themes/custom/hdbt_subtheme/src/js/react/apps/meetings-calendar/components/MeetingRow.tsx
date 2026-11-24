import Icon from '@/react/common/Icon';
import { formatDayFull } from '../helpers/date';
import type { Meeting } from '../types/Meeting';

interface MeetingRowProps {
  meeting: Meeting;
  date: Date;
}

const getLink = (meeting: Meeting): { href: string; label: string } | null => {
  if (meeting.decisionLink) {
    return { href: meeting.decisionLink, label: Drupal.t('Open decision announcement') };
  }
  if (meeting.minutesLink) {
    return { href: meeting.minutesLink, label: Drupal.t('Open minutes') };
  }
  if (meeting.motionsListLink) {
    return { href: meeting.motionsListLink, label: Drupal.t('Open agenda') };
  }
  return null;
};

export const MeetingRow = ({ meeting, date }: MeetingRowProps) => {
  const link = getLink(meeting);

  return (
    <div
      className={`meetings-calendar__meeting${meeting.meetingCancelled ? ' meetings-calendar__meeting--cancelled' : ''}`}
    >
      <h4 className='meetings-calendar__meeting__title'>
        {meeting.policymakerName}
        {meeting.meetingMoved && <span> ({Drupal.t('meeting moved', {}, { context: 'Meetings calendar' })})</span>}
        {!meeting.meetingMoved && meeting.meetingCancelled && (
          <span> ({Drupal.t('meeting cancelled', {}, { context: 'Meetings calendar' })})</span>
        )}
      </h4>
      <div className='meetings-calendar__meeting__start-time'>{meeting.startTime}</div>
      {link && (
        <a href={link.href} aria-label={`${link.label}: ${meeting.title} ${formatDayFull(date)}`}>
          {link.label}
          <Icon icon='angle-right' />
        </a>
      )}
    </div>
  );
};
