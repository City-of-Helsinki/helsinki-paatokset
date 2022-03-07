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

    $('body').on('click', '.decision-navigation-button', function(event) {
      event.preventDefault();
      const link = event.target.tagName === 'A' ?
        $(event.target).data('link') :
        $(event.target).parent('a').data('link');

      console.log(link);
      console.log($(optionList).find(`[data-link='${link}']`));

      selectOption($(optionList).find(`[data-link='${link}']`));
    });
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
    if($(selected).data('link') === $(handle).data('link')) {
      closeOptions();
      return;
    }

    if($(selected).length > 0) {
      transformButton(selected);
      $('.issue__meetings-dropdown .hds-button__label').html($(selected).text());
      $('.issue__meetings-select li.selected').attr('aria-selected', 'false');
      $('.issue__meetings-select li.selected').removeClass('selected');
      $(selected).addClass('selected');
      $(selected).attr('aria-selected', 'true');
      closeOptions();
    }

    loadDecision($(selected).data('link'));
  }

  function transformButton(selected) {
    const styleClass = $(selected).data('styleclass');
    const link = $(selected).data('link');

    const buttonClasses = [
      'hds-button',
      'hds-button--secondary',
      styleClass
    ];

    $(handle).removeClass();
    buttonClasses.forEach(function(className) {
      $(handle).addClass(className);
    })
    $(handle).data('link', link);
  }

  /**
   * Load decision content via ajax and update URL
   */
  function loadDecision(id) {
    const caseId = $('#case-header').data('caseId')
    const { baseUrl, pathPrefix, currentPath } = window.drupalSettings.path;
    const path = `${baseUrl}${pathPrefix}ahjo_api/case/${caseId}`;

    $.ajax({
      url: `${path}?decision=${id}`,
      beforeSend: function() {
        $('.issue__wrapper .ajax-progress-throbber').show();
      },
      success: function(response) {
        const data = JSON.parse(response);

        if (data.content) {
          $('.issue__ajax-container').html(data.content);
        }
        if (data.attachments) {
          $('.issue__attachments__wrapper').html(data.attachments);
        }
        if (data.decision_navigation) {
          $('.issue__decision-navigation__wrapper').html(data.decision_navigation);
        }
        if (data.all_decisions_link) {
          $('.issue-dropdown__show-more a').attr('href', data.all_decisions_link);
          $('.issue-dropdown__show-more a span').text(Drupal.t('Other decisions for the meeting'));
          $('.issue-dropdown__show-more').css('display', 'block');
        }
        else if (data.other_decisions_link) {
          $('.issue-dropdown__show-more a').attr('href', data.other_decisions_link);
          $('.issue-dropdown__show-more a span').text(Drupal.t('Other decisions for the policymaker'));
          $('.issue-dropdown__show-more').css('display', 'block');
        }
        else {
          $('.issue-dropdown__show-more').css('display', 'none');
        }
        if (data.decision_pdf) {
          $('.issue__pdf a').attr('href', data.decision_pdf);
          $('.issue__pdf').css('display', 'block');
        } else {
          $('.issue__pdf').css('display', 'none');
        }
        if (data.show_warning) {
          showWarning();
        }
        else {
          hideWarning();
        }
        $('.issue-ajax-error__container').hide();
      },
      error: function() {
        $('.issue-ajax-error__container').show();
      },
      complete: function() {
        $('.issue__wrapper .ajax-progress-throbber').hide();
        window.document.dispatchEvent(new Event("DOMContentLoaded", {
          bubbles: true,
          cancelable: true
        }));
      }
    });

    window.history.pushState({}, '', `${window.location.pathname}?decision=${id}`);
  }

  function hideWarning() {
    $('.issue__new-handlings-warning').removeClass('visible');
  }

  function showWarning() {
    $('.issue__new-handlings-warning').addClass('visible');
  }
});
