import { useAtomValue } from 'jotai';
import { useAtomCallback } from 'jotai/utils';
import { useCallback, useRef } from 'react';
import useSWR from 'swr';
import useTimeoutFetch from '@/react/common/hooks/useTimeoutFetch';
import { formQuery, matchTypeLabel } from '../helpers';
import { getElasticUrlAtom, selectionsAtom, urlAtom } from '../store';
import { FormContainer } from './FormContainer';
import { ResultsContainer } from './ResultsContainer';

export const SearchContainer = () => {
  const url = useAtomValue(urlAtom);
  const elasticUrl = useAtomValue(getElasticUrlAtom);
  const typeOptions = useRef(undefined);
  const readSelections = useAtomCallback(useCallback((get) => get(selectionsAtom), []));

  const fetcher = async () => {
    const queryBody = formQuery(readSelections());

    if (typeOptions.current) {
      // biome-ignore lint/correctness/useHookAtTopLevel: @todo UHF-12501
      const response = await useTimeoutFetch(`${elasticUrl}/paatokset_allu/_search`, {
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
    const response = await useTimeoutFetch(`${elasticUrl}/paatokset_allu/_msearch`, {
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

  const { data, error, isLoading } = useSWR(url || `${elasticUrl}/paatokset_allu`, fetcher, {
    revalidateOnFocus: false,
  });

  return (
    <>
      <FormContainer typeOptions={typeOptions.current} />
      <ResultsContainer {...{ data, error, isLoading }} />
    </>
  );
};
