import useSWRImmutable from 'swr/immutable';
import { useAtom } from 'jotai';

import { FormContainer } from './containers/FormContainer';
import { ResultsContainer } from './containers/ResultsContainer';
import { aggsAtom } from './store';
import { useAggsQueryString } from './hooks/useAggsQueryString';

export const DecisionsContainer = ({
  url,
}: {
  url: string
}) => {
  const [aggs, setAggs] = useAtom(aggsAtom);
  const aggsQueryString = useAggsQueryString();
  
  const fetcher = async() => {
    if (aggsQueryString.includes('\n')) {
      const response = await fetch(`${url}/paatokset_decisions,paatokset_policymakers/_msearch`, {
        body: aggsQueryString,
        headers: {
          'Content-Type': 'application/x-ndjson',
        },
        method: 'POST',
      });

      const results = await response.json();
      return {
        aggregations: results.responses.reduce((acc: Record<string, any>, res: {aggregations: Record<string, any>}) => ({
          ...acc,
          ...res.aggregations,
        }), {}),
      };
    }

    return fetch(`${url}/paatokset_decisions/_search`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
      },
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
      <FormContainer url={url} />
      <ResultsContainer url={url} />
    </>
  );
};
