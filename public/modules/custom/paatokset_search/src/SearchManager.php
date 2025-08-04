<?php

declare(strict_types=1);

namespace Drupal\paatokset_search;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\node\NodeInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Search manager class.
 */
class SearchManager {

  /**
   * Constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory.
   * @param \Drupal\Core\Language\LanguageManagerInterface $languageManager
   *   The language manager.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   */
  public function __construct(
    private readonly ConfigFactoryInterface $configFactory,
    private readonly LanguageManagerInterface $languageManager,
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {
  }

  /**
   * Return markup for search page.
   *
   * @param string $type
   *   The type of search page. Should one of the following:
   *   'decisions', 'policymakers' or 'frontpage'.
   * @param array $classes
   *   The classes to add to the search page.
   *
   * @return array
   *   The render array.
   */
  public function build($type, $classes = []): array {
    $proxyUrl = $this->configFactory->get('elastic_proxy.settings')->get('elastic_proxy_url') ?: '';
    $operatorGuideUrl = $this->getOperatorGuideUrl();
    $defaultTexts = $this->configFactory->get('paatokset_ahjo_api.default_texts');

    $defaultTexts = [
      'description' => $defaultTexts->get('decision_search_description.value'),
    ];

    $build = [
      '#type' => 'html_tag',
      '#tag' => 'div',
      '#attributes' => [
        'id' => 'paatokset_search',
        'data-type' => $type,
        'data-url' => $proxyUrl,
        'data-operator-guide-url' => $operatorGuideUrl,
      ],
      '#attached' => [
        'library' => [
          'hdbt_subtheme/decisions-search',
        ],
        'drupalSettings' => [
          'paatokset_search' => [
            'default_texts' => $defaultTexts,
          ],
        ],
      ],
    ];

    if ($classes) {
      $build['#attributes']['class'] = $classes;
    }

    if ($sentryDsnReact = $this->configFactory->get('paatokset_search.settings')->get('sentry_dsn_react')) {
      $build['#attached']['drupalSettings']['paatokset_react_search']['sentry_dsn_react'] = $sentryDsnReact;
    }

    return $build;
  }

  /**
   * Get operator guide URL.
   *
   * @return string
   *   The operator guide URL or empty string if the node does not exist or
   *   user does not have access.
   */
  private function getOperatorGuideUrl(): string {
    $currentLanguage = $this->languageManager->getCurrentLanguage();
    // Operator guide node id is set in an environment variable
    // OPERATOR_GUIDE_NODE_ID.
    $operatorGuideNodeId = $this->configFactory->get('paatokset_search.settings')->get('operator_guide_node_id');
    $operatorGuideNode = $operatorGuideNodeId
      ? $this->entityTypeManager->getStorage('node')->load($operatorGuideNodeId)
      : NULL;
    $operatorGuideUrl = $operatorGuideNode instanceof NodeInterface && $operatorGuideNode->access('view')
      ? $operatorGuideNode->toUrl('canonical', ['language' => $currentLanguage])->toString()
      : '';

    return $operatorGuideUrl;
  }

}
