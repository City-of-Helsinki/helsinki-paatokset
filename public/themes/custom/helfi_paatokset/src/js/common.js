(($, Drupal, drupalSettings) => {
  Drupal.behaviors.asuAdminCommon = {
    attach: function attach() {
      // Code here.
    }
  };

  Drupal.behaviors.languageSwitcher = {
    attach: function attach(context) {
      const languageSwitcherToggleButton = $(
        ".language-switcher__button",
        context
      );
      const languageSwitcherWrapper = $(
        ".language-switcher__dropdown",
        context
      );

      const outsideClickListener = function outsideClickListener(event) {
        const target = $(event.target);

        if (
          !target.closest(".language-switcher__dropdown").length &&
          $(".language-switcher__dropdown").is(":visible")
        ) {
          // eslint-disable-next-line no-use-before-define
          handleInteraction(event);
          // eslint-disable-next-line no-use-before-define
          removeClickListener();
        }
      };

      const removeClickListener = function removeClickListener() {
        document.removeEventListener("click", outsideClickListener);
      };

      function handleInteraction(e) {
        e.stopImmediatePropagation();

        if (languageSwitcherWrapper.hasClass("is-active")) {
          languageSwitcherWrapper
            .removeClass("is-active")
            .attr("aria-hidden", "true");
          languageSwitcherToggleButton.attr("aria-expanded", "false");
        } else {
          languageSwitcherWrapper
            .addClass("is-active")
            .attr("aria-hidden", "false");
          languageSwitcherToggleButton.attr("aria-expanded", "true");
          document.addEventListener("click", outsideClickListener);
        }
      }

      languageSwitcherToggleButton.on({
        click: function touchstartclick(e) {
          handleInteraction(e);
        },
        keydown: function keydown(e) {
          if (e.which === 27) {
            languageSwitcherWrapper
              .removeClass("is-active")
              .attr("aria-hidden", "true");
            languageSwitcherToggleButton.attr("aria-expanded", "false");
            removeClickListener();
          }
        }
      });
    }
  };
})(jQuery, Drupal, drupalSettings);
