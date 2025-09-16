import React from 'react';
import ReactDOM from 'react-dom';
import { ErrorBoundary } from '@sentry/react';
import { Select } from 'hds-react';

import ResultsError from '@/react/common/ResultsError';

const parent = document.getElementById('helfi-select');
const element = parent?.querySelector('input[type="hidden"]') as HTMLInputElement;
const rootElement = parent?.appendChild(document.createElement('div'));

const selectOptions = Object.entries(drupalSettings.helfi_select.options).reverse().map(([key, value]) => ({
  label: value,
  value: key,
  selected: value === drupalSettings.helfi_select.value,
}));

if (rootElement) {
  ReactDOM.render(
    <React.StrictMode>
      <ErrorBoundary fallback={<ResultsError error='Select element crashed' />}>
        <Select
          texts={{
            label: Drupal.t('Year'),
            placeholder: Drupal.t('All years'),
            language: drupalSettings.path.currentLanguage,
          }}
          options={selectOptions}
          onChange={element ? (options) => {
            let value = options?.find(item => item.selected)?.value ?? '';
            if (value === drupalSettings.helfi_select.empty_option) {
              value = '';
            }

            element.value = value;
          } : undefined}
        />
      </ErrorBoundary>
    </React.StrictMode>,
    rootElement
  );
}
