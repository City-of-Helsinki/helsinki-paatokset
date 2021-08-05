class PaatoksetDropdown {
  constructor(config = {}) {
    if(config.customClickHandler) {
      this.customClickHandler = config.customClickHandler;
    }

    this.selectors = {};
    if(config.selectors) {
      this.selectors = config.selectors;
    }

    if(document.readyState === 'complete' || document.readyState === 'interactive') {
      const that = this;
      this._init(that);
    }
    else {
      const that = this;
      document.addEventListener('DOMContentLoaded', function() {
        that._init(that);
      });
    }
  };

  _init(that) {
    that.handle = document.querySelector(that.selectors.handle);
    that.optionList = document.querySelector(that.selectors.optionList);
    that.options = document.querySelectorAll(that.selectors.options);
    that.container = document.querySelector(that.selectors.container)
    that.dropdown = document.querySelector(that.selectors.dropdown);
    that.focusIndex = -1;

    that.selectedOption = that.container.querySelector('.selected');

    // Add the default style class to button
    const defaultSelected = that.optionList.querySelector('li.selected');
    if(defaultSelected) {
      that.transformButton(defaultSelected);
    }

    // Clear event listeners (fixes issues with ajax forms)
    if(that.handle.fn) {
      that.handle.removeEventListener('click', that.handle.fn, false);
    }

    that.handle.addEventListener('click', that.handle.fn=function() {
      if(that.optionsVisible()) {
        that.closeOptions();
      }
      else {
        that.openOptions();
      }
    });

    that.handle.addEventListener('keydown', function(event) {
      if(event.key && (event.key === 'ArrowDown' || event.key === 'ArrowUp')) {
        if(!that.optionsVisible()) {
          that.openOptions();
        }
      }
    });

    // Close dropdown if focus moves outside parent
    that.container.addEventListener('focusout', function(event) {
      if(!that.container.contains(event.relatedTarget)) {
        that.closeOptions(false);
      }
    });

    that.options.forEach(option => {
      ['click', 'keydown'].forEach(eventType => {
        option.addEventListener(eventType, function(event) {
          // Prevent triggering handle's click event
          event.preventDefault();
          if(!event.key || event.key === 'Enter') {
            that.selectOption(event.target);
          }
        });
      });

      option.addEventListener('focus', function(event) {
        that.focusIndex = [].indexOf.call(event.target.parentElement.children, event.target);
      });
    })

    that.dropdown.addEventListener('keydown', function(event) {
      if(event.key && event.key === 'Escape') {
        that.closeOptions(false);
      }
    });

    that.container.addEventListener('keydown', function(event) {
      if(!event.key || !(event.key === 'ArrowUp' || event.key === 'ArrowDown')) {
        return;
      }
      event.preventDefault();
      if(event.key === 'ArrowUp' && that.focusIndex > -1) {
        that.focusIndex--;
      }
      if(event.key === 'ArrowDown' && that.focusIndex < that.options.length -1) {
        that.focusIndex++;
      }
      if(that.focusIndex >= 0 && that.focusIndex < that.options.length) {
        that.options[that.focusIndex].focus();
      }
      if(that.focusIndex === -1) {
        that.handle.focus();
      }
    });
  };
  
  optionsVisible() {
    return window.getComputedStyle(this.optionList).display === 'block';
  }

  transformButton(selected) {
    const styleClass = selected.dataset.styleclass;
    const link = selected.dataset.link;

    const buttonClasses = [
      'hds-button',
      'hds-button--secondary',
    ];

    if(styleClass) {
      buttonClasses.push(styleClass);
    }

    this.handle.classList = [];
    const self = this;
    buttonClasses.forEach(function(className) {
      self.handle.classList.add(className);
    })
    this.handle.dataset.link = link;
  }

  openOptions() {
    this.optionList.style.display = 'block';
    this.optionList.setAttribute('aria-expanded', 'true');
    this.dropdown.classList.add('open');
  }

  closeOptions() {
    this.optionList.style.display = 'none';
    this.optionList.setAttribute('aria-expanded', 'false');
    this.dropdown.classList.remove('open');;
  }

  selectOption(option) {
    let selected = option;
    if(!option.dataset.link) {
      if(option.parentElement.dataset.link) {
        selected = option.parentElement;
      }
    }

    if(selected.dataset.link === this.handle.dataset.link) {
      this.closeOptions();
      return;
    }

    this.transformButton(selected);
    this.handle.querySelector('.hds-button__label').innerHTML = option.querySelector('a').innerHTML;
    this.setSelectedOption(selected);
    this.closeOptions();

    const self = this;
    setTimeout(function() {
      self.customClickHandler(self.selectedOption.dataset.link);
    }, 1)
  }

  setSelectedOption(selected) {
    const previous = this.selectedOption;
    this.selectedOption = selected;

    if(previous) {
      previous.setAttribute('aria-selected', 'false');
      previous.classList.remove('selected');
    }

    this.selectedOption.setAttribute('aria-selected', 'true');
    this.selectedOption.classList.add('selected');
  }

  // Initiate empty function to be overriden
  customClickHandler() {
    return;
  };
}

class PaatoksetDatepicker extends PaatoksetDropdown {
  _init(that) {
    that.handle = document.querySelector(that.selectors.handle);
    that.optionList = document.querySelector(that.selectors.optionList);
    that.options = document.querySelectorAll(that.selectors.options);
    that.container = document.querySelector(that.selectors.container)
    that.dropdown = document.querySelector(that.selectors.dropdown);
    that.closer = that.optionList.querySelector('.datepicker__closer');
    that.focusIndex = -1;

    that.selectedOption = that.container.querySelector('.selected');

    // Add the default style class to button
    const defaultSelected = that.optionList.querySelector('li.selected');
    if(defaultSelected) {
      that.transformButton(defaultSelected);
    }

    // Clear event listeners (fixes issues with ajax forms)
    if(that.handle.fn) {
      that.handle.removeEventListener('click', that.handle.fn, false);
    }

    that.handle.addEventListener('click', that.handle.fn = function() {
      if(that.optionsVisible()) {
        that.closeOptions();
      }
      else {
        that.openOptions();
      }
    });

    that.handle.addEventListener('keydown', function(event) {
      if(event.key && (event.key === 'ArrowDown' || event.key === 'ArrowUp')) {
        if(!that.optionsVisible()) {
          that.openOptions();
        }
      }
    });

    that.closer.addEventListener('click', function() {
      that.closeOptions();
    })

    // Close dropdown if focus moves outside parent
    // that.container.addEventListener('focusout', function(event) {
    //   if(!that.container.contains(event.relatedTarget)) {
    //     that.closeOptions(false);
    //   }
    // });

    // that.options.forEach(option => {
    //   ['click', 'keydown'].forEach(eventType => {
    //     option.addEventListener(eventType, function(event) {
    //       // Prevent triggering handle's click event
    //       event.preventDefault();
    //       if(!event.key || event.key === 'Enter') {
    //         that.selectOption(event.target);
    //       }
    //     });
    //   });

    //   option.addEventListener('focus', function(event) {
    //     that.focusIndex = [].indexOf.call(event.target.parentElement.children, event.target);
    //   });
    // })

    // that.dropdown.addEventListener('keydown', function(event) {
    //   if(event.key && event.key === 'Escape') {
    //     that.closeOptions(false);
    //   }
    // });

    // that.container.addEventListener('keydown', function(event) {
    //   if(event.key === 'ArrowUp' && that.focusIndex > -1) {
    //     that.focusIndex--;
    //   }
    //   if(event.key === 'ArrowDown' && that.focusIndex < that.options.length -1) {
    //     that.focusIndex++;
    //   }
    //   if(that.focusIndex >= 0 && that.focusIndex < that.options.length) {
    //     that.options[that.focusIndex].focus();
    //   }
    //   if(that.focusIndex === -1) {
    //     that.handle.focus();
    //   }
    // });
  }

  selectOption() {
    return;
  }
}

/**
 * Load decision content via ajax and update URL
 */

function callback(id) {
  $ = jQuery;
  const { baseUrl, pathPrefix, currentPath } = window.drupalSettings.path;
  const path = `${baseUrl}${pathPrefix}${currentPath}`;
  $.ajax({
    url: `${path}/ajax?decision=${id}`,
    beforeSend: function() {
      $('.issue__wrapper .ajax-progress-throbber').show();
    },
    success: function(response) {
      const data = JSON.parse(response);
      
      if(data.content) {
        $('.issue__ajax-container').html(data.content);
      }
      if(data.attachments) {
        $('.issue__container .issue-right-column__container').html(data.attachments);
      }
      if(data.decision_navigation) {
        $('.issue__decision-navigation').html(data.decision_navigation);
      }
      if(data.all_decisions_link) {
        $('.issue-dropdown__show-more a').attr('href', data.all_decisions_link);
      }
      if(data.show_warning) {
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
    }
  });
  window.history.pushState({}, '', `${path}?decision=${id}`);
}


function hideWarning() {
  $('.issue__new-handlings-warning').removeClass('visible');
}

function showWarning() {
  $('.issue__new-handlings-warning').addClass('visible');
}

const selector = '.issue__wrapper';
if(document.querySelector(`${selector} .issue__meetings-container`)) {
  const dropdown = new PaatoksetDropdown({
    selectors: {
      handle: `${selector} .issue__meetings-dropdown button`,
      optionList: `${selector} .issue__meetings-select`,
      options: `${selector} .issue__meetings-select li`,
      container: `${selector} .issue__meetings-container`,
      dropdown: `${selector} .issue__meetings-dropdown`,
    },
    customClickHandler: callback
  });
}

(function($, Drupal, drupalSettings) {
  Drupal.behaviors.PaatoksetDropdown = {
    attach: function(context, settings) {
      Drupal.PaatoksetDropdown = PaatoksetDropdown;
      Drupal.PaatoksetDatepicker = PaatoksetDatepicker;
    }
  }
})(jQuery, Drupal, drupalSettings);