(($, Drupal, once) => {
  function handleListVisibility() {
    const headerContainer = $(
      '.paatokset__decision-tree-container .tabbed-list',
    );
    const containerWidth = headerContainer.width();
    const menu = headerContainer.find('ul.menu');
    const dropdown = $(headerContainer).find('.custom-select-wrapper');

    $(dropdown).removeClass('hidden');
    const adjustingOffset = containerWidth * 0.25;
    const allowedWidth = containerWidth - dropdown.width() - adjustingOffset;

    const items = $(menu).find('li').toArray();
    let itemsWidth = 0;
    let exceeded = false;
    const allowedItems = [];
    const selectedFirst = items.sort(
      (a, b) => $(b).hasClass('selected') - $(a).hasClass('selected'),
    );

    selectedFirst.forEach((item) => {
      itemsWidth += $(item).width();
      exceeded = itemsWidth >= allowedWidth;

      if (exceeded && allowedItems.length > 0) {
        $(item).addClass('hidden');
      } else {
        allowedItems.push($(item).find('input').attr('value'));
        $(item).removeClass('hidden');
      }

      $(item).find('input').attr('aria-pressed', $(item).hasClass('selected'));
    });

    if (exceeded) {
      $(dropdown).removeClass('hidden');
      const dropdownItems = $(
        '.custom-select-wrapper div.custom-option',
      ).toArray();

      dropdownItems.forEach((item) => {
        if (allowedItems.includes($(item).find('input').attr('value'))) {
          $(item).addClass('hidden');
        } else {
          $(item).removeClass('hidden');
        }
      });
    } else {
      $(dropdown).addClass('hidden');
    }
  }

  function handleDropdownToggle(event) {
    if ($(event.target).parents('.custom-select-wrapper').length > 0) {
      $(event.target).parents('.custom-select').toggleClass('open');
    } else {
      $('.custom-select-wrapper .custom-select').removeClass('open');
    }
  }

  function showSelected() {
    const selectedValue = $('.tabbed-list__content__inner .selected')
      .find('input[type="button"]')
      .val();

    $('.policymakers-documents, .policymakers-decisions').removeClass(
      'selected-year',
    );
    $(
      `.policymakers-documents[value="${selectedValue}"], .policymakers-decisions[value="${selectedValue}"]`,
    ).addClass('selected-year');
  }

  function handleSelect(event) {
    let value;
    if (event.target.type === 'button') {
      value = $(event.target).val();
    } else {
      const inputElement = $(event.target).find('input');
      if (inputElement) {
        value = $(inputElement).val();
      }
    }

    if (value) {
      // Remove all prior selected classes
      $('.tabbed-list__content__inner ul.menu li').removeClass('selected');
      $('.custom-select-wrapper .custom-option').removeClass('selected');
      $('.custom-select-wrapper .custom-option input').attr(
        'aria-pressed',
        false,
      );

      // Add selected class to selected item
      $(`.tabbed-list__content__inner ul.menu input[value="${value}"]`)
        .parent('li')
        .addClass('selected');
      $(`.custom-select-wrapper .custom-option input[value="${value}"]`)
        .parent('.custom-option')
        .addClass('selected');
      $(`.custom-select-wrapper .custom-option input[value="${value}"]`).attr(
        'aria-pressed',
        'true',
      );

      // Handle narrow views
      handleListVisibility();
    }

    showSelected();
  }

  Drupal.behaviors.myBehavior = {
    attach(context) {
      once('paatokset_submenus', 'html', context).forEach(() => {
        handleListVisibility();
        window.addEventListener('resize', handleListVisibility);
        $(document).click(handleDropdownToggle);
        $(context)
          .find('.tabbed-list__content__inner input')
          .click(handleSelect);
        $(context).find('#custom-options').click(handleSelect);
      });
    },
  };
})(jQuery, Drupal, once);
