import useSWRImmutable from 'swr/immutable';
import { useAtom } from 'jotai';

import { FormContainer } from './containers/FormContainer';
import { ResultsContainer } from './containers/ResultsContainer';
import { aggsAtom } from './store';
import { useAggsQueryString } from './hooks/useAggsQueryString';

export const PolicymakerContainer = ({ url }: { url: string }) => {
  const [aggs, setAggs] = useAtom(aggsAtom);
  const aggsQueryString = useAggsQueryString();

  const fetcher = async () => {
    const response = await fetch(`${url}/paatokset_policymakers/_search`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: aggsQueryString,
    });
    return response.json();
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
