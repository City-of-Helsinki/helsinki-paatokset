jQuery(function($) {
  const handle = $('.issue__meetings-dropdown button');
  const optionList = $('.issue__meetings-select');
  const options = $('.issue__meetings-select li');
  const container = $('.issue__meetings-container');
  const dropdown = $('.issue__meetings-dropdown');
  const socialMedia = $('.social-media__items a');
  let focusIndex = -1;

  $(document).ready(function() {
    $('.issue-ajax-error__container .hds-notification__label').attr('aria-hidden', 'true');
    // Add the default style class to button
    const defaultSelected = $('.issue__meetings-select li.selected');

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
      });
      $(handle).data('link', link);
    }
    
    function openOptions() {
      $(optionList).show();
      $(optionList).attr('aria-expanded', 'true');
      $(dropdown).addClass('open');
    }

    function closeOptions(focusHandle = true) {
      $(optionList).hide();
      $(optionList).attr('aria-expanded', 'false');
      $(dropdown).removeClass('open');
  
      if(focusHandle) {
        $(handle).focus();
      }
    }

    function arrowHandler(event) {
      if(!event.key || !(event.key === 'ArrowUp' || event.key === 'ArrowDown')) {
        return;
      }
      if (event.key === 'ArrowUp' && focusIndex > -1) {
        focusIndex -= 1;
      }
      if(event.key === 'ArrowDown' && focusIndex < options.length -1) {
        focusIndex += 1;
      }
      if(focusIndex >= 0 && focusIndex < options.length) {
        $(options)[focusIndex].focus();
      }
      if(focusIndex === -1) {
        handle.focus();
      }
    }

    function hideWarning() {
      $('.issue__new-handlings-warning').removeClass('visible');
    }

    function showWarning() {
      $('.issue__new-handlings-warning').addClass('visible');
    }

    /**
     * Load decision content via AJAX and update URL.
     *
     * @param {string} id - The decision ID to load.
     */
    function loadDecision(id) {
      const caseId = $('#case-header').data('caseId');
      const { baseUrl, pathPrefix } = window.drupalSettings.path;
      const path = `${baseUrl}${pathPrefix}ahjo_api/case/${caseId}/${id}`;


      $.ajax({
        url: path,
        beforeSend() {
          $('.issue__wrapper .ajax-progress-throbber').show();
        },
        success(data) {
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
          if (data.language_urls) {
            Object.keys(data.language_urls).forEach(langcode => {
              $(`.language-link[lang="${langcode}"]`).attr('href', data.language_urls[langcode]);
            });
          }
          if (data.show_warning) {
            showWarning();
          }
          else {
            hideWarning();
          }
          $('.issue-ajax-error__container').hide();
        },
        error() {
          $('.issue-ajax-error__container .hds-notification__label').attr('aria-hidden', 'false');
          $('.issue-ajax-error__container').attr('aria-hidden', 'false').show();
        },
        complete() {
          $('.issue__wrapper .ajax-progress-throbber').hide();
          window.document.dispatchEvent(new Event('DOMContentLoaded', {
            bubbles: true,
            cancelable: true
          }));
        }
      });

      let queryKey = 'paatos';
      if (window.drupalSettings.path.currentLanguage === 'sv') {
        queryKey = 'beslut';
      }
      else if (window.drupalSettings.path.currentLanguage === 'en') {
        queryKey = 'decision';
      }

      let queryparams = window.location.search;
      if (queryparams === '') {
        queryparams = `?${  queryKey  }=`;
      }
      const oldWindowHref = window.location.host + window.location.pathname + queryparams;

      window.history.pushState({}, '', `${window.location.pathname}?${queryKey}=${id}`);

      socialMedia.each(() => {
        const oldHref =  $(this).attr('href');
        const newWindowHref = window.location.host + window.location.pathname + window.location.search;
        $(this).attr('href', oldHref.replace(oldWindowHref, newWindowHref));
      });
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

    if(defaultSelected.length) {
      transformButton($(defaultSelected)[0]);
    }

    handle.on('click', function() {
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
    });

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

      selectOption($(optionList).find(`[data-link='${link}']`));
    });
  });
});
