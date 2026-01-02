((Drupal, once) => {
  let abortController = new AbortController();

  const minCharAssistiveHint = Drupal.t(
    'Type @count or more characters for results',
    {},
    { context: 'Frontpage decision search' },
  );
  const inputAssistiveHint = Drupal.t(
    'When autocomplete results are available use up and down arrows to review and enter to select. Touch device users, explore by touch or with swipe gestures.',
    {},
    { context: 'Frontpage decision search' },
  );
  const noResultsAssistiveHint = Drupal.t(
    'No decision suggestions were found',
    {},
    { context: 'Frontpage decision search' },
  );
  const someResultsAssistiveHint = Drupal.t(
    'There are @count results available.',
    {},
    { context: 'Frontpage decision search' },
  );
  const oneResultAssistiveHint = Drupal.t(
    'There is one result available.',
    {},
    { context: 'Frontpage decision search' },
  );
  const highlightedAssistiveHint = Drupal.t(
    '@selectedItem @position of @count is highlighted',
    {},
    { context: 'Frontpage decision search' },
  );

  /**
   * Initialize autocomplete.
   *
   * @param {HTMLSelectElement} element Select element.
   */
  const init = (element) => {
    // eslint-disable-next-line no-undef
    if (!A11yAutocomplete) {
      throw new Error('A11yAutocomplete object not found. Make sure the library is loaded.');
    }

    const defaultOptions = [];

    // Set by '#autocomplete_route_name'.
    const autocompleteRoute = element.dataset.autocompletePath;

    // eslint-disable-next-line no-undef
    const autocomplete = A11yAutocomplete(element, {
      classes: { inputLoading: 'loading', wrapper: 'helfi-location-autocomplete' },
      highlightedAssistiveHint,
      inputAssistiveHint,
      minCharAssistiveHint,
      minChars: 3,
      noResultsAssistiveHint,
      oneResultAssistiveHint,
      someResultsAssistiveHint,
      source: async (searchTerm, results) => {
        if (searchTerm.length < 3) {
          return results(defaultOptions);
        }

        try {
          abortController.abort();
          abortController = new AbortController();

          const response = await fetch(`${autocompleteRoute}?q=${searchTerm}`, { signal: abortController.signal });

          const data = await response.json();
          results(defaultOptions.concat(data));
        } catch (e) {
          if (e.name === 'AbortError') {
            return;
          }

          // eslint-disable-next-line no-console
          console.error(e);
          results(defaultOptions);
        }
      },
    });
    const autocompleteInstance = autocomplete._internal_object;

    // Opens the dropdown on focus when input is empty
    // Not supported by the a11y-autocomplete library
    element.addEventListener('focus', () => {
      if (autocompleteInstance.input.value === '' && defaultOptions.length) {
        autocompleteInstance.displayResults(defaultOptions);
      }
    });
    // Similar to above, allow opening list with arrow keys
    element.addEventListener('keydown', (event) => {
      if (
        autocompleteInstance.input.value === '' &&
        defaultOptions.length &&
        autocompleteInstance.suggestions.length === 0 &&
        event.key === 'ArrowDown'
      ) {
        autocompleteInstance.displayResults(defaultOptions);
      }
    });

    const specialCharWrapper = element.closest('.hds-text-input').querySelector('.decisions-search-bar-wrapper__label-status');
    if (specialCharWrapper) {
      element.addEventListener('input', () => {
        const hasSpecialChars = /[“”"()+|*-]/.test(element.value);
        specialCharWrapper.hidden = !hasSpecialChars;
      });
    }
  };

  Drupal.behaviors.paatokset_textfield_autocomplete = {
    attach(context) {
      once('a11y_autocomplete_element', '[data-paatokset-textfield-autocomplete]', context).forEach(init);
    },
  };
})(Drupal, once);
