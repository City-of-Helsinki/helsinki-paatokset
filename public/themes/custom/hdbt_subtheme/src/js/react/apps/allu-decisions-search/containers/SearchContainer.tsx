import { useCallback, useRef } from 'react';
import useSWR from 'swr';
import { useAtomValue } from 'jotai';
import { useAtomCallback } from 'jotai/utils';

import { FormContainer } from './FormContainer';
import { ResultsContainer } from './ResultsContainer';
import useTimeoutFetch from '@/react/common/hooks/useTimeoutFetch';
import { formQuery, matchTypeLabel } from '../helpers';
import { selectionsAtom, urlAtom } from '../store';

const DATA_ENDPOINT = `${drupalSettings.helfi_react_search.elastic_proxy_url}/paatokset_allu`;

export const SearchContainer = () => {
  const url = useAtomValue(urlAtom);
  const typeOptions = useRef(undefined);
  const readSelections = useAtomCallback(useCallback((get) => get(selectionsAtom), []));

  const fetcher = async () => {
    const queryBody = formQuery(readSelections());

    if (typeOptions.current) {
      // biome-ignore lint/correctness/useHookAtTopLevel: @todo UHF-12501
      const response = await useTimeoutFetch(`${DATA_ENDPOINT}/_search`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(queryBody),
      });

      const json = await response.json();

      return json;
    }

    // Include aggs request to get filter options
    const ndjsonHeader = '{}';
    // biome-ignore lint/correctness/useHookAtTopLevel: @todo UHF-12501
    const response = await useTimeoutFetch(`${DATA_ENDPOINT}/_msearch`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-ndjson' },
      body: `${ndjsonHeader}\n${JSON.stringify({
        aggs: { typeOptions: { terms: { field: 'document_type', size: 500000 } } },
      })}\n${ndjsonHeader}\n${JSON.stringify(queryBody)}\n`,
    });

    const json = await response.json();
    const [aggs, results] = json.responses;

    if (aggs.aggregations?.typeOptions?.buckets) {
      typeOptions.current = aggs.aggregations.typeOptions.buckets.map(
        // biome-ignore lint/suspicious/noExplicitAny: @todo UHF-12501
        (bucket: any) => ({ label: matchTypeLabel(bucket.key), value: bucket.key }),
      );
    }

    return results;
  };

  const { data, error, isLoading } = useSWR(url || DATA_ENDPOINT, fetcher, { revalidateOnFocus: false });

  return (
    <>
      <FormContainer typeOptions={typeOptions.current} />
      <ResultsContainer {...{ data, error, isLoading }} />
    </>
  );
};
