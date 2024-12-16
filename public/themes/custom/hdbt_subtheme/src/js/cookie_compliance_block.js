// @todo UHF-10862 Remove once the HDBT cookie banner module is in use.
(($, Drupal) => {
  Drupal.behaviors.paatoksetCookieComplianceBlock = {
    attach: function attach() {
      $(document).ready(() => {
        const totalCategories = $(
          "input.hds-checkbox__input",
          ".eu-cookie-compliance-block-form"
        );
        const categories = Drupal.eu_cookie_compliance.getAcceptedCategories();
        let selected = 0;
        $.each(categories, (key, value) => {
          $(`[data-drupal-selector="edit-categories-${value}"]`).prop(
            "checked",
            true
          );
          // eslint-disable-next-line no-plusplus
          selected++;
        });

        if (selected < totalCategories.length) {
          $('[data-drupal-selector="edit-accept-all"]')
            .parent()
            .removeClass("hidden");
        }
      });
    },
  };
})(jQuery, Drupal);
