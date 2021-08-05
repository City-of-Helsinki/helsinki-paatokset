
import Litepicker from 'litepicker';
import moment from 'moment';

// Context to use with Drupal.t()
var translationContext = 'Decisions search form';

(function ($, Drupal, drupalSettings) {

  Drupal.behaviors.paatoksetSearchForms = {
    attach: function(context, settings) {
      // Function for creating checkbox-style dropdowns
      const S2CheckedDropdown = function (options, element) {
        const self = this;
        self.options = options;
        self._element = $(element);
        const values = self._element.val();
        self._element.removeAttr('multiple');
        self.select2 = self._element.select2({
          closeOnSelect: false,
          width: '100%',
          theme: 'paatokset',
          allowClear: true,
          multiple: true,
          templateSelection: function(state) {
            return self.options.templateSelection(self._element.val(), state);
          },
          templateResult: function(state) {
            if(!state.id) {
              return state.text;
            }
    
            const checked = state.selected ? 'checked' : '';
            return $(`<div><span class="checkbox ${checked}"><span aria-hidden="true" class="hds-icon hds-icon--check"></span></span>${state.text}</div>`);
          }
        }).data('select2');
        self.select2.$results.off("mouseup").on("mouseup", ".select2-results__option[aria-selected]", (function(self) {
          return function(evt) {
            var $this = $(this);
      
            const Utils = $.fn.select2.amd.require('select2/utils')
            var data = Utils.GetData(this, 'data');
    
            if ($this.attr('aria-selected') === 'true') {
              self.trigger('unselect', {
                originalEvent: evt,
                data: data
              });
              return;
            }
    
            self.trigger('select', {
              originalEvent: evt,
              data: data
            });
          }
        })(self.select2));
        self._element.attr('multiple', 'multiple').val(values).trigger('change.select2');
      }
  
      // Extends $.fn with our function
      $.fn.extend({
        paatoksetCheckedDropdown: function() {
          const options = $.extend({
            templateSelection: function(selected, state) {
              return selected.length ?
                $(`
                  <div class="hds-tag select2-selection__choice__remove">
                    <span class="hds-icon hds-icon--cross"></span>
                    <span class="hds-tag__label">${state.text}</span>
                  </div>
                `) :
                element.select2.options.options.placeholder;
            }
          }, arguments[0]);
  
          this.each(function() {
            new S2CheckedDropdown(options, this);
          })
        }
      });

      // Transform checkbox-style dropdowns
      $('.paatokset-checked-dropdown', context).paatoksetCheckedDropdown();

      // Transform date field
      const dateField = $('.advanced-search__date-field', context).select2({
        closeOnSelect: true,
        minimumResultsForSearch: -1,
        width: '100%',
        theme: 'paatokset',
        templateResult: function(state) {
          if(!state.id) {
            return state.text;
          }
  
          const checked = state.selected ? 'checked' : '';
          return $(`<div><span class="checkbox ${checked}"><span aria-hidden="true" class="hds-icon hds-icon--check"></span></span>${state.text}</div>`);
        }
      }).on('select2:open', function() {
        const dropdown = $(this).data('select2').$dropdown;
        if(dropdown && $(dropdown).find('.toggle-calendar-option').length < 1) {
          $(dropdown).find('.select2-results').append(
            `
            <div class="select2-results__option">
              <button class="toggle-calendar-option hds-button hds-button--supplementary">
                <span class="hds-icon hds-icon--calendar-clock"></span>
                ${Drupal.t('Choose range', {}, {context: translationContext})}
                <span class="hds-icon hds-icon--angle-right">
              </button>
            </li>
            `
          );
        }
  
        $('.toggle-calendar-option').on('click', function(event) {
          event.preventDefault();
          $('input[name="advanced-search__date-format"]').val(0);
        }) 
      });

      // Initiate datepicker dropdown
      const dpDropdown = '.date-picker-container';
      $(context).once('initdatepicker').each(function() {
        if(context.querySelector(dpDropdown)) {
          new Drupal.PaatoksetDatepicker({
            selectors: {
              handle: `${dpDropdown} .issue__meetings-dropdown button`,
              optionList: `${dpDropdown} .issue__meetings-select`,
              options: `${dpDropdown} .issue__meetings-select li`,
              container: `${dpDropdown} .issue__meetings-container`,
              dropdown: `${dpDropdown} .issue__meetings-dropdown`,
            }
          });
        }
      })

      // Update actual drupal datefields when input changes
      function updateDateFields() {
        $('.datepicker-fields input').each(function() {
          if($(this).val()) {
            $(this).addClass('has-input');
            const value = $(this).val();
            const name = $(this).attr('name');
            const date = moment(value, 'DD.MM.YYYY');
            if(date.isValid()) {
              $(`.date-range__container input[type="date"].${name}`).val(date.format('YYYY-MM-DD'));
              $(this).parents('.hds-text-input').removeClass('hds-text-input--invalid')
            } else {
              $(`.date-range__container input[type="date"].${name}`).val(null);
              $(this).parents('.hds-text-input').addClass('hds-text-input--invalid');
            }
          } else {
            $(this).removeClass('has-input');
          }
        });
      }

      // Create datepicker instance
      const widgetRoot = $('.datepicker__widget').length;
      if(widgetRoot && !$(widgetRoot).find('.litepicker').length) {
        Drupal.DecisionsSearchDatePicker = new Litepicker({
          element: document.querySelector('.datepicker__widget'),
          singleMode: false,
          format: 'DD.MM.YYYY',
          lang: drupalSettings.path.currentLanguage,
          setup: function(picker) {
            picker.on('selected', function(from, to) {
              $('input[name="date-from"]').val(moment(from.toJSDate()).format('DD.MM.YYYY'));
              $('input[name="date-to"]').val(moment(to.toJSDate()).format('DD.MM.YYYY'));
              updateDateFields();
            })
          },
          inlineMode: true
        });
      }

      // Register event listeners for toggling date selection type
      $('body', context).on('click', '.toggle-calendar-option', function() {
        const useCalendar = $('input[name="advanced-search__use-calendar"]');
        $(dateField).select2('close');
        useCalendar.prop('checked', true).trigger('change');
        $(`${dpDropdown} .issue__meetings-dropdown .opener button`).click();
      });

      $('body', context).on('click', '.navigate-back', function() {
        const useCalendar = $('input[name="advanced-search__use-calendar"]');
        useCalendar.prop('checked', false).trigger('change');
        $(dateField).select2('open');
      });

      // Register event handlers when date fields are manually changed
      $('.datepicker-fields input', context).on('change', function() {
        const date = moment($(this).val(), 'DD.MM.YYYY');
        updateDateFields();
        if(date.isValid()) {
          noSelect = true;
          const dateString = date.format('DD.MM.YYYY');
          switch($(this).attr('name')) {
            case 'date-from':
              const endDate = Drupal.DecisionsSearchDatePicker.getEndDate();
              
              Drupal.DecisionsSearchDatePicker.setDateRange(
                dateString,
                endDate ? endDate : dateString
              );
              Drupal.DecisionsSearchDatePicker.gotoDate(Drupal.DecisionsSearchDatePicker.getStartDate());
              break;
            case 'date-to':
              const startDate = Drupal.DecisionsSearchDatePicker.getStartDate();
              Drupal.DecisionsSearchDatePicker.setDateRange(
                startDate ? startDate : dateString,
                dateString
              );
              Drupal.DecisionsSearchDatePicker.gotoDate(Drupal.DecisionsSearchDatePicker.getEndDate());
              break;
          }
        }
      });

      // Transform view controls
      $('.views-exposed-form.bef-exposed-form select', context).each(function() {
        $(this).select2({
          minimumResultsForSearch: -1,
          width: '100%',
          theme: 'paatokset',
        });
      });

      // Change default handle to hds-icon
      $('.select2-selection__arrow', context).append('<span class="hds-icon hds-icon--angle-down"></span>');

      // Add angle-down icon to multiple 
      $('.select2-selection--multiple', context).append('<span class="select2-selection__arrow" role="presentation"><b role="presentation"></b><span class="hds-icon hds-icon--angle-down"></span></span>'); 

      // Event listener for tag click (remove selection)
      $('.paatokset-tag--unselect', context).on('click', function(event) {
        event.preventDefault();
        let selector = '';
        switch($(this).data('origin')) {
          case 'policymaker': 
            selector = 'select[name="policymakers[]"]';
            break;
          case 'topic':
            selector = 'select[name="topics[]"]';
            break;
          case 'org_types':
            selector = 'select[name="org-type[]"';
            break;
          default:
            return;
        }
        let values = $(selector).val();
        const target = $(this).data('key');
        const index = values.indexOf(target);
        if(index > -1) {
          values.splice(index, 1);
        }
        $(selector).val(values).trigger('change');
      });

      // Event listener for the clear all button
      $('.paatokset-tag-container > .hds-button--supplementary', context).on('click', function() {
        ['select[name="policymakers[]"]', 'select[name="topics[]"]'].forEach(function(selector) {
          $(selector).val(null);
        });

        // Trigger change only once
        $('select[name="policymakers[]"]').trigger('change');
      });
    }
  };
})(jQuery, Drupal, drupalSettings);
