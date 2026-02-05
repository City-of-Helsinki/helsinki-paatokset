import useSWRImmutable from 'swr/immutable';
import { useAtom, useAtomValue } from 'jotai';

import { FormContainer } from './containers/FormContainer';
import { ResultsContainer } from './containers/ResultsContainer';
import { aggsAtom, getElasticUrlAtom } from './store';
import { useAggsQueryString } from './hooks/useAggsQueryString';

export const PolicymakerContainer = () => {
  const [aggs, setAggs] = useAtom(aggsAtom);
  const url = useAtomValue(getElasticUrlAtom);
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
      <FormContainer />
      <ResultsContainer />
    </>
  );
};
