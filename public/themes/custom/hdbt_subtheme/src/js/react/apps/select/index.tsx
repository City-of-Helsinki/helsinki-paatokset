import React from 'react';
import ReactDOM from 'react-dom';
import { ErrorBoundary } from '@sentry/react';
import { Select } from 'hds-react';

import ResultsError from '@/react/common/ResultsError';
import { defaultSelectTheme } from '@/react/common/constants/selectTheme';

Drupal.behaviors.helfiSelect = {
  attach: (context: HTMLElement) => {
    const parent = context.querySelector('#helfi-select');
    const element = parent?.querySelector('input[type="hidden"]') as HTMLInputElement;
    if (!parent || !element) {
      return;
    }

    const rootElement = parent.appendChild(document.createElement('div'));
    const settings: HelfiSelectSettings = JSON.parse(element.dataset.helfiSelectSettings ?? 'null');

    const selectOptions = Object.entries(settings.options)
      .reverse()
      .map(([key, value]) => ({ label: value, value: key, selected: value === settings.value }));

    if (rootElement) {
      ReactDOM.render(
        <React.StrictMode>
          <ErrorBoundary fallback={<ResultsError error='Select element crashed' />}>
            <Select
              texts={{
                label: Drupal.t('Year'),
                placeholder: Drupal.t('All years', {}, { context: 'Year filter' }),
                language: drupalSettings.path.currentLanguage,
              }}
              theme={defaultSelectTheme}
              options={selectOptions}
              onChange={
                element
                  ? (options) => {
                      let value = options?.find((item) => item.selected)?.value ?? '';
                      if (value === settings.empty_option) {
                        value = '';
                      }
                      element.value = value;
                    }
                  : undefined
              }
            />
          </ErrorBoundary>
        </React.StrictMode>,
        rootElement,
      );
    }
  },
};
