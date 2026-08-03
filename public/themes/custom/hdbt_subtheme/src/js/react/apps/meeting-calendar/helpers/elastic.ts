import type { Meeting, MeetingsByDate } from '../types/Meeting';
import { getHelsinkiParts } from './date';

// Search API index id 'meetings' + the 'paatokset_' prefix configured on the default
// Elasticsearch server (conf/cmi/search_api.server.default.yml) -> real ES index name.
const MEETINGS_INDEX = 'paatokset_meetings';

declare const ELASTIC_DEV_URL: string | undefined;

const getElasticUrl = (): string => {
  const devUrl = typeof ELASTIC_DEV_URL !== 'undefined' ? ELASTIC_DEV_URL : '';
  return devUrl || drupalSettings?.helfi_react_search?.elastic_proxy_url || '';
};

const getLangcode = (): 'fi' | 'en' | 'sv' => drupalSettings?.path?.currentLanguage ?? 'fi';

interface MeetingDmData {
  type?: string;
  title?: Partial<Record<'fi' | 'en' | 'sv', string>>;
}

interface MeetingUrlData {
  meeting_link?: Partial<Record<'fi' | 'en' | 'sv', string>>;
  decision_link?: Partial<Record<'fi' | 'en' | 'sv', string>>;
}

interface MeetingHit {
  _source: {
    title: string[];
    field_meeting_date: number[];
    field_meeting_date_original?: number[];
    field_meeting_status: string[];
    field_meeting_dm_id: string[];
    meeting_phase?: string[];
    meeting_dm_data?: string[];
    meeting_url?: string[];
  };
}

interface MeetingsSearchResponse {
  hits: { hits: MeetingHit[] };
}

const buildMeetingsQuery = (fromDate: Date) => ({
  query: {
    bool: {
      must: [{ range: { field_meeting_date: { gte: Math.floor(fromDate.getTime() / 1000) } } }],
    },
  },
  sort: [{ field_meeting_date: { order: 'asc' } }],
  size: 10000,
});

const parseJson = <T>(raw: string | undefined): T | undefined => {
  if (!raw) {
    return undefined;
  }
  try {
    return JSON.parse(raw) as T;
  } catch {
    return undefined;
  }
};

// The previous PHP endpoint always hard-coded 'only_future_cancelled' - past cancelled
// meetings are dropped, upcoming cancelled ones are still shown (struck through).
const mapHit = (hit: MeetingHit, langcode: 'fi' | 'en' | 'sv', nowSeconds: number): Meeting | null => {
  const source = hit._source;
  const timestamp = source.field_meeting_date[0];
  const origTimestamp = source.field_meeting_date_original?.[0];
  const meetingCancelled = source.field_meeting_status[0] === 'peruttu';

  if (meetingCancelled && timestamp < nowSeconds) {
    return null;
  }

  const meetingMoved = !meetingCancelled && Boolean(origTimestamp) && origTimestamp !== timestamp;
  const phase = source.meeting_phase?.[0];

  const dmData = parseJson<MeetingDmData>(source.meeting_dm_data?.[0]);
  const policymakerName = dmData?.title?.[langcode];

  let decisionLink: string | undefined;
  let minutesLink: string | undefined;
  let motionsListLink: string | undefined;

  // Cancelled meetings never carry a meaningful meeting_url, matching the previous endpoint.
  if (!meetingCancelled) {
    const urlData = parseJson<MeetingUrlData>(source.meeting_url?.[0]);
    if (phase === 'minutes' && urlData?.meeting_link?.[langcode]) {
      minutesLink = urlData.meeting_link[langcode];
    } else if (phase === 'decision' && urlData?.decision_link?.[langcode]) {
      decisionLink = urlData.decision_link[langcode];
    } else if (phase === 'agenda' && urlData?.meeting_link?.[langcode]) {
      motionsListLink = urlData.meeting_link[langcode];
    }
  }

  return {
    title: source.title[0],
    policymaker: source.field_meeting_dm_id[0],
    policymaker_name: policymakerName,
    meeting_moved: meetingMoved,
    meeting_cancelled: meetingCancelled,
    start_time: getHelsinkiParts(timestamp).time,
    decision_link: decisionLink,
    minutes_link: minutesLink,
    motions_list_link: motionsListLink,
  };
};

export const fetchMeetings = async (fromDate: Date): Promise<MeetingsByDate> => {
  const response = await fetch(`${getElasticUrl()}/${MEETINGS_INDEX}/_search`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(buildMeetingsQuery(fromDate)),
  });
  const json: MeetingsSearchResponse = await response.json();
  const langcode = getLangcode();
  const nowSeconds = Math.floor(Date.now() / 1000);

  const meetingsByDate: MeetingsByDate = {};

  json.hits.hits.forEach((hit) => {
    const meeting = mapHit(hit, langcode, nowSeconds);
    if (!meeting) {
      return;
    }
    const { dateKey } = getHelsinkiParts(hit._source.field_meeting_date[0]);
    if (!meetingsByDate[dateKey]) {
      meetingsByDate[dateKey] = [];
    }
    meetingsByDate[dateKey].push(meeting);
  });

  return meetingsByDate;
};
