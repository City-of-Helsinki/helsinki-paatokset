(function ($, Drupal) {

  // Replace current URL with one containing query string, refresh results view
  Drupal.AjaxCommands.prototype.submitSearch = function (ajax, response, status) {
    const { selector, url, view } = response;
    window.history.pushState({path: url}, '', url)
    $(selector).html(view);
  }

})(jQuery, Drupal);
