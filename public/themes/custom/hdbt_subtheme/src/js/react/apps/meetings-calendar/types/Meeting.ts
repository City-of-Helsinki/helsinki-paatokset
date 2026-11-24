export interface Meeting {
  title: string;
  policymaker: string;
  policymakerName: string | null;
  meetingMoved: boolean;
  meetingCancelled: boolean;
  startTime: string;
  decisionLink: string | null;
  minutesLink: string | null;
  motionsListLink: string | null;
}

export type MeetingsByDate = Record<string, Meeting[]>;

export interface CalendarDay {
  date: string;
  meetings: Meeting[];
}
