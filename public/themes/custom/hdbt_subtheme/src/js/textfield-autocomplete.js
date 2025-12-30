let abortController = new AbortController();

((Drupal, once) => {
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

    const parent = element.closest('.hds-text-input');

    const formItemLabel = document.querySelector('.form-type-paatokset-textfield-autocomplete .hds-text-input__label');

    const specialCharRegex = /[“”"()+|*-]/;

    const specialCharachters = Drupal.t(
      'Your search contains special characters, such as “ or +. For more results, try searching without special characters.',
      {},
      { context: 'Frontpage decision search' },
    );

    const specialCharLinkText = Drupal.t(
      'Learn more about using special characters.',
      {},
      { context: 'Frontpage decision search' },
    );

    const specialCharWrapper = document.createElement('div');
    let specialCharHint = parent.querySelector('.paatokset-special-char-hint');
    let specialCharLink = parent.querySelector('.paatokset-special-char-hint-link');

    if (!specialCharHint) {
      specialCharWrapper.className = 'decisions-search-bar-wrapper__label-status';
      specialCharHint = document.createElement('span');
      specialCharHint.className = 'paatokset-special-char-hint';
      specialCharHint.textContent = specialCharachters;
      specialCharHint.setAttribute('role', 'status');
      specialCharHint.setAttribute('aria-live', 'polite');
      specialCharWrapper.hidden = true;
      specialCharWrapper.appendChild(specialCharHint);
      formItemLabel.after(specialCharWrapper);
      if (!specialCharLink) {
        specialCharLink = document.createElement('a');
        specialCharLink.className = 'paatokset-special-char-hint-link';
        specialCharLink.href = '/test'; //TODO: Replace with actual link
        specialCharLink.textContent = specialCharLinkText;
        specialCharHint.appendChild(specialCharLink);
      }
    }

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

    // Set by '#autocomplete_route_name'.
    const autocompleteRoute = element.dataset.autocompletePath;

    // eslint-disable-next-line no-undef
    const autocomplete = A11yAutocomplete(element, {
      classes: { inputLoading: 'loading', wrapper: 'helfi-location-autocomplete' },
      highlightedAssistiveHint,
      inputAssistiveHint,
      minCharAssistiveHint,
      minChars: 0,
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

    element.addEventListener('input', () => {
      const hasSpecialChars = specialCharRegex.test(element.value);
      specialCharWrapper.hidden = !hasSpecialChars;
    });
  };

  Drupal.behaviors.paatokset_textfield_autocomplete = {
    attach(context) {
      once('a11y_autocomplete_element', '[data-paatokset-textfield-autocomplete]', context).forEach(init);
    },
  };
})(Drupal, once);
