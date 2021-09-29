(function ($, Drupal, once) {
  function handleListVisibility() {
    const headerContainer = $('.paatokset__decision-tree-container .accordion__wrapper.handorgel');
    const containerWidth = headerContainer.width();
    const menu = headerContainer.find('ul.menu');
    const dropdown = $(headerContainer).find('.custom-select-wrapper');

    $(dropdown).removeClass('hidden');
    const allowedWidth = containerWidth - dropdown.width() - 50;

    const items = $(menu).find('li').toArray();
    let itemsWidth = 0;
    let exceeded = false;
    const allowedItems = [];
    for(item of items) {
      itemsWidth += $(item).width();
      exceeded = itemsWidth >= allowedWidth;

      if(exceeded && allowedItems.length > 0) {
        $(item).addClass('hidden');
      } else {
        allowedItems.push($(item).find('input').attr('value'));
        $(item).removeClass('hidden');
      }
    }

    if(exceeded) {
      $(dropdown).removeClass('hidden');
      const dropdownItems = $('.custom-select-wrapper div.custom-option').toArray();

      for(item of dropdownItems) {
        if(allowedItems.includes($(item).find('input').attr('value'))) {
          $(item).addClass('hidden');
        } else {
          $(item).removeClass('hidden');
        }
      }
    } else {
      $(dropdown).addClass('hidden');
    }
  }

  function handleDropdownToggle(event) {
    if($(event.target).parents('.custom-select-wrapper').length > 0) {
      $(event.target).parents('.custom-select').toggleClass('open');
    }
    else {
      $('.custom-select-wrapper .custom-select').removeClass('open');
    }
  }

  function showSelected() {
    const selectedValue = $('.handorgel__content__inner .selected').find('input[type="button"]').val();

    $('.policymakers-documents, .policymakers-decisions').removeClass('selected-year');
    $(`.policymakers-documents[value="${selectedValue}"], .policymakers-decisions[value="${selectedValue}"]`).addClass('selected-year');
  }

  function handleSelect(event) {
    let value;
    if(event.target.type === 'button') {
      value = $(event.target).val();
    }
    else {
      const inputElement = $(event.target).find('input');
      if(inputElement) {
        value = $(inputElement).val();
      }
    }

    if(value) {
      // Remove all prior selected classes
      $('.handorgel__content__inner ul.menu li').removeClass('selected');
      $('.custom-select-wrapper .custom-option').removeClass('selected');

      // Add selected class to selected item
      $(`.handorgel__content__inner ul.menu input[value="${value}"]`).parent('li').addClass('selected');
      $(`.custom-select-wrapper .custom-option input[value="${value}"]`).parent('.custom-option').addClass('selected');
    }

    showSelected();
  }

  Drupal.behaviors.myBehavior = {
    attach: function (context) {
      once('paatokset_submenus', 'html', context).forEach( function () {
        handleListVisibility();
        window.addEventListener('resize', handleListVisibility);
        $(document).click(handleDropdownToggle);
        $(context).find('.accordion-item__content__inner.handorgel__content__inner input').click(handleSelect);
      })
    }
  }
}(jQuery, Drupal, once));
