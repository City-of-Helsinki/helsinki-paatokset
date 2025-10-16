<?php

namespace Drupal\paatokset_search\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Drupal\paatokset_search\SearchManager;

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
  public function __construct(
    private readonly SearchManager $searchManager,
  ) {
  }

  /**
   * Return markup for search page.
   */
  public function decisions(): array {
    $baseBuild = $this->searchManager->build('decisions', ['paatokset-search--decisions']);
    $description = [
      $this->t('The City of Helsinki primarily conducts its decision-making  in Finnish and Swedish. Although you can browse this service in English, the decision-making documents themselves are available in Finnish and Swedish only.', [], ['context' => 'Decisions search']),
      $this->t('Enter a keyword in the search field to find a specific decision. Results can be filtered by date, topic and decision-maker.', [], ['context' => 'Decisions search']),
      $this->t('If several decisions have been made on the same issue, the search result will initially show only the best match. To view the entire decision-making history associated with the issue, click to open the decision.', [], ['context' => 'Decisions search']),
    ];

    $build = array_merge($baseBuild, [
      '#description' => [
        '#type' => 'container',
        '#children' => array_map(fn ($text) => [
          '#type' => 'html_tag',
          '#tag' => 'p',
          '#value' => $text,
        ], $description),
      ],
      '#theme' => 'decisions_search',
    ]);

    $operatorGuideUrl = $this->searchManager->getOperatorGuideUrl();
    if (!empty($operatorGuideUrl)) {
      $build['#description']['#children'][] = [
        '#type' => 'link',
        '#title' => $this->t('Read the instructions for refining your search.', [], ['context' => 'Decisions search']),
        '#url' => Url::fromUserInput($operatorGuideUrl),
      ];
    }

    return $build;
  }

}
