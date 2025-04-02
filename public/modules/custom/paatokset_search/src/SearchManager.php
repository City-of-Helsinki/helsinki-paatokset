<?php

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
   * The search config.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  private $proxyConfig;

  /**
   * The search config.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  private $searchConfig;

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
    $this->proxyConfig = $this->configFactory->get('elastic_proxy.settings');
    $this->searchConfig = $this->configFactory->get('paatokset_search.settings');
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
    $proxyUrl = $this->proxyConfig->get('elastic_proxy_url') ?: '';
    $operatorGuideUrl = $this->getOperatorGuideUrl();

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
          'paatokset_search/paatokset-search',
        ],
      ],
    ];

    if ($classes) {
      $build['#attributes']['class'] = $classes;
    }

    if ($sentryDsnReact = $this->searchConfig->get('sentry_dsn_react')) {
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
    $operatorGuideNodeId = $this->searchConfig->get('operator_guide_node_id');
    $operatorGuideNode = $operatorGuideNodeId
      ? $this->entityTypeManager->getStorage('node')->load($operatorGuideNodeId)
      : NULL;
    $operatorGuideUrl = $operatorGuideNode instanceof NodeInterface && $operatorGuideNode->access('view')
      ? $operatorGuideNode->toUrl('canonical', ['language' => $currentLanguage])->toString()
      : '';

    return $operatorGuideUrl;
  }

}
