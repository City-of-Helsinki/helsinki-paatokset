export interface Meeting {
  title: string;
  policymaker: string;
  policymaker_name?: string;
  meeting_moved: boolean;
  meeting_cancelled: boolean;
  start_time: string;
  decision_link?: string;
  minutes_link?: string;
  motions_list_link?: string;
}

export type MeetingsByDate = Record<string, Meeting[]>;
