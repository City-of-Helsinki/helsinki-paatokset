// eslint-disable-next-line func-names
(function ($, Drupal) {

  const loadMatomoAnalytics = () => {
    // Load Matomo only if statistics cookies are allowed.
    if (Drupal.cookieConsent.getConsentStatus(['statistics'])) {
      // Matomo Tag Manager
      // eslint-disable-next-line no-multi-assign
      const _mtm = (window._mtm = window._mtm || []);
      _mtm.push({
        'mtm.startTime': new Date().getTime(),
        event: 'mtm.Start',
      });
      const d = document;
      const g = d.createElement('script');
      const s = d.getElementsByTagName('script')[0];
      g.type = 'text/javascript';
      g.async = true;
      g.src = '//webanalytics.digiaiiris.com/js/container_iNUwkZOx.js';

      s.parentNode.insertBefore(g, s);
    }
  };

  // Load when cookie settings are changed.
  if (Drupal.cookieConsent.initialized()) {
    loadMatomoAnalytics();
  } else {
    Drupal.cookieConsent.loadFunction(loadMatomoAnalytics);
  }
})(jQuery, Drupal);
