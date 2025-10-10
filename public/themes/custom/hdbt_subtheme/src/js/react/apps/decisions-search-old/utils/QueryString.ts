export const getQueryParam = (param: string) => {
  const params = new URLSearchParams(window.location.search);

  return params.get(param);
};

export const updateQueryParam = (param: string, value: string) => {
  const params = new URLSearchParams(window.location.search);
  params.set(param, value);
  const newUrl = `${window.location.protocol}//${window.location.host}${window.location.pathname}?${params.toString()}`;
  window.history.pushState({
    path: newUrl
  }, '', newUrl); 
};

export const deleteQueryParam = (param: string) => {
  const params = new URLSearchParams(window.location.search);
  params.delete(param);
  let newUrl = `${window.location.protocol}//${window.location.host}${window.location.pathname}`;

  if(params.toString().length > 0) {
    newUrl += `?${params.toString()}`; 
  }

  window.history.pushState({
    path: newUrl
  }, '', newUrl); 
};
