jQuery(function($) {
  const handle = $('.issue__meetings-dropdown button');
  const optionList = $('.issue__meetings-select');
  const options = $('.issue__meetings-select li');
  const container = $('.issue__meetings-container')
  const dropdown = $('.issue__meetings-dropdown');
  var focusIndex = -1;

  $(document).ready(function() {
    // Add the default style class to button
    const defaultSelected = $('.issue__meetings-select li.selected');
    if(defaultSelected.length) {
      transformButton($(defaultSelected)[0]);
    }

    handle.on('click', function(event) {
      optionList.toggle();
      optionList.attr('aria-expanded', function(i, value) {
        return value === 'true' ? 'false' : 'true';
      });
      dropdown.toggleClass('open');
    });

    handle.on('keydown', function(event) {
      if(event.key && (event.key === 'ArrowDown' || event.key === 'ArrowUp')) {
        if(optionList.css('display') === 'none') {
          openOptions();
        }
      }
    })

    // Close dropdown if focus moves outside parent
    $(container).focusout(function(event) {
      if(!$(this).has(event.relatedTarget).length) {
        closeOptions(false);
      }
    });

    options.on('click keydown', function(event) {
      // Prevent triggering handle's click event
      event.preventDefault();
      if(!event.key || event.key === 'Enter') {
        selectOption($(this));
      }
    });

    dropdown.on('keydown', function(event) {
      if(event.key && event.key === 'Escape') {
        closeOptions(false);
      }
    });

    options.on('focus', function() {
      focusIndex = $(options).index($(this));
    });

    container.on('keydown', arrowHandler);
  });

  function closeOptions(focusHandle = true) {
    $(optionList).hide();
    $(optionList).attr('aria-expanded', 'false');
    $(dropdown).removeClass('open');

    if(focusHandle) {
      $(handle).focus();
    }
  }

  function openOptions() {
    $(optionList).show();
    $(optionList).attr('aria-expanded', 'true');
    $(dropdown).addClass('open');
  }

  function arrowHandler(event) {
    if(!event.key || !(event.key === 'ArrowUp' || event.key === 'ArrowDown')) {
      return;
    }
    if(event.key === 'ArrowUp' && focusIndex > -1) {
      focusIndex--;
    }
    if(event.key === 'ArrowDown' && focusIndex < options.length -1) {
      focusIndex++;
    }
    if(focusIndex >= 0 && focusIndex < options.length) {
      $(options)[focusIndex].focus();
    }
    if(focusIndex === -1) {
      handle.focus();
    }
  }

  function selectOption(selected) {
    if($(selected)[0].dataset.link === $(handle)[0].dataset.link) {
      closeOptions();
      return;
    }

    if($(selected).length > 0) {
      transformButton($(selected)[0]);
      $('.issue__meetings-dropdown .hds-button__label').html($(selected).text());
      $('.issue__meetings-select li.selected').attr('aria-selected', 'false');
      $('.issue__meetings-select li.selected').removeClass('selected');
      $(selected).addClass('selected');
      $(selected).attr('aria-selected', 'true');
      closeOptions();
    }
  }

  function transformButton(selected) {
    const styleClass = selected.dataset.styleclass;
    const link = selected.dataset.link;

    const buttonClasses = [
      'hds-button',
      'hds-button--secondary',
      styleClass
    ];

    $(handle).removeClass();
    buttonClasses.forEach(function(className) {
      $(handle).addClass(className);
    })
    $(handle)[0].dataset.link = link;
  }
});
