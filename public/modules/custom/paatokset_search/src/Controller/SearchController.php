<?php

namespace Drupal\paatokset_search\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\paatokset_search\SearchManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Controller class for decisions search page.
 */
class SearchController extends ControllerBase {

  /**
   * Controller for policymaker subpages.
   *
   * @param \Drupal\paatokset_search\SearchManager $searchManager
   *   The search manager.
   */
  final public function __construct(
    private SearchManager $searchManager,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get(SearchManager::class),
    );
  }

  /**
   * Return markup for search page.
   */
  public function decisions(): array {
    return $this->searchManager->build('decisions', ['paatokset-search--decisions']);
  }

}
