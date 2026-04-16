import { useAtom, useAtomValue } from 'jotai';
import useSWRImmutable from 'swr/immutable';

import { FormContainer } from './containers/FormContainer';
import { ResultsContainer } from './containers/ResultsContainer';
import { useAggsQueryString } from './hooks/useAggsQueryString';
import { aggsAtom, getElasticUrlAtom } from './store';

export const DecisionsContainer = () => {
  const [aggs, setAggs] = useAtom(aggsAtom);
  const url = useAtomValue(getElasticUrlAtom);
  const aggsQueryString = useAggsQueryString();

  const fetcher = async () => {
    if (aggsQueryString.includes('\n')) {
      const response = await fetch(`${url}/paatokset_decisions,paatokset_policymakers/_msearch`, {
        body: aggsQueryString,
        headers: { 'Content-Type': 'application/x-ndjson' },
        method: 'POST',
      });

      const results = await response.json();
      return {
        aggregations: results.responses.reduce(
          (
            // biome-ignore lint/suspicious/noExplicitAny: @todo UHF-12501
            acc: Record<string, any>,
            // biome-ignore lint/suspicious/noExplicitAny: @todo UHF-12501
            res: { aggregations: Record<string, any> },
          ) => ({
            // biome-ignore lint/performance/noAccumulatingSpread: @todo UHF-12501
            ...acc,
            ...res.aggregations,
          }),
          {},
        ),
      };
    }

    return fetch(`${url}/paatokset_decisions/_search`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: aggsQueryString,
    }).then((res) => res.json());
  };

  const { data } = useSWRImmutable(aggsQueryString, fetcher);

  if (!aggs && data && data.aggregations) {
    setAggs(data.aggregations);
  }

  if (!aggs) {
    return null;
  }

  return (
    <>
      <FormContainer />
      <ResultsContainer />
    </>
  );
};
