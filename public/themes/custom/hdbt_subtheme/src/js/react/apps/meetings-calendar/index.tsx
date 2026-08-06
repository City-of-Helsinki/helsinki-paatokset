import { ErrorBoundary } from '@sentry/react';
import React from 'react';
import { createRoot } from 'react-dom/client';
import initSentry from '@/react/common/helpers/Sentry';
import ResultsError from '@/react/common/ResultsError';
import { MeetingCalendarContainer } from './containers/MeetingCalendarContainer';

initSentry();

const ROOT_ID = 'meetings-calendar';

const start = () => {
  const rootElement = document.getElementById(ROOT_ID);

  if (!rootElement) {
    console.warn('Root id missing for Meeting calendar app', { ROOT_ID });
    return;
  }

  createRoot(rootElement).render(
    <React.StrictMode>
      <ErrorBoundary fallback={<ResultsError error='Meeting calendar crashed' />}>
        <MeetingCalendarContainer />
      </ErrorBoundary>
    </React.StrictMode>,
  );
};

document.addEventListener('DOMContentLoaded', start);
