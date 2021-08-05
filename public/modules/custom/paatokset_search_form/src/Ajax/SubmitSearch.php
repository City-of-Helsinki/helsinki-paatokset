<?php

namespace Drupal\paatokset_search_form\Ajax;

use Drupal\Core\Ajax\CommandInterface;

/**
 * Submitsearch action. Updates view via Ajax after submit.
 */
class SubmitSearch implements CommandInterface {
  /**
   * Selector for frontend element.
   *
   * @var selector
   */
  private $selector;
  /**
   * Url to update the current one with.
   *
   * @var url
   */
  private $url;
  /**
   * Rendered view.
   *
   * @var view
   */
  private $view;

  /**
   * Class constructor.
   *
   * @param string $selector
   *   Selector to set.
   * @param string $url
   *   Url to set.
   * @param string $view
   *   Rendered view to set.
   */
  public function __construct(string $selector = '', string $url = '', string $view = '') {
    $this->selector = $selector;
    $this->url = $url;
    $this->view = $view;
  }

  /**
   * Render AJAX command.
   *
   * @return array
   *   Array containing commmand
   */
  public function render() {
    return [
      'command' => 'submitSearch',
      'selector' => $this->selector,
      'url' => $this->url,
      'view' => $this->view,
    ];
  }

  /**
   * Setter for selector.
   */
  public function setSelector(string $selector) {
    $this->selector = $selector;
  }

  /**
   * Setter for params.
   */
  public function setUrl(string $url) {
    $this->url = $url;
  }

  /**
   * Setter for view.
   */
  public function setView(string $view) {
    $this->view = $view;
  }

}
