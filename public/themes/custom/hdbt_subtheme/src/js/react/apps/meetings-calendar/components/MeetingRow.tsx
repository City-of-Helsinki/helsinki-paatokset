import Icon from '@/react/common/Icon';
import { formatDayFull } from '../helpers/date';
import type { Meeting } from '../types/Meeting';

interface MeetingRowProps {
  meeting: Meeting;
  date: Date;
}

const getLink = (meeting: Meeting): { href: string; label: string } | null => {
  if (meeting.decision_link) {
    return { href: meeting.decision_link, label: Drupal.t('Open decision announcement') };
  }
  if (meeting.minutes_link) {
    return { href: meeting.minutes_link, label: Drupal.t('Open minutes') };
  }
  if (meeting.motions_list_link) {
    return { href: meeting.motions_list_link, label: Drupal.t('Open agenda') };
  }
  return null;
};

export const MeetingRow = ({ meeting, date }: MeetingRowProps) => {
  const link = getLink(meeting);

  return (
    <div
      className={`meetings-calendar__meeting${meeting.meeting_cancelled ? ' meetings-calendar__meeting--cancelled' : ''}`}
    >
      <h4 className='meetings-calendar__meeting__title'>
        {meeting.policymaker_name}
        {meeting.meeting_moved && <span> ({Drupal.t('meeting moved', {}, { context: 'Meetings calendar' })})</span>}
        {!meeting.meeting_moved && meeting.meeting_cancelled && (
          <span> ({Drupal.t('meeting cancelled', {}, { context: 'Meetings calendar' })})</span>
        )}
      </h4>
      <div className='meetings-calendar__meeting__start-time'>{meeting.start_time}</div>
      {link && (
        <a href={link.href} aria-label={`${link.label}: ${meeting.title} ${formatDayFull(date)}`}>
          {link.label}
          <Icon icon='angle-right' />
        </a>
      )}
    </div>
  );
};
