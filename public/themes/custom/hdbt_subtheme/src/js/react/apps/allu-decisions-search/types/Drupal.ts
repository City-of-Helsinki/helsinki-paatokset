// eslint-disable-next-line
declare namespace Drupal {
  const cookieConsent: {
    initialized: () => boolean;
    loadFunction: (callback: () => void) => void;
    getConsentStatus: (categories: string[]) => string;
    setAcceptedCategories: (categories: string[]) => void;
  };
  function t(str: string, options?: object, context?: object): string;
  function formatPlural(count: string, singular: string, plural: string, args?: object, options?: object): string;
  function theme(id: string): string;
};
