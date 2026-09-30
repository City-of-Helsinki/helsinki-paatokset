<?php

declare(strict_types=1);

namespace Drupal\paatokset_allu\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\helfi_api_base\Environment\ActiveServiceTrait;
use Drupal\helfi_api_base\Environment\EnvironmentResolverInterface;

/**
 * Paragraph hooks for the Allu decisions search.
 */
final class ParagraphHooks {

  use ActiveServiceTrait;

  public function __construct(
    protected readonly EnvironmentResolverInterface $environmentResolver,
  ) {
  }

  /**
   * Implements hook_preprocess_paragraph__HOOK().
   *
   * @param array<string, mixed> $variables
   *   The template variables.
   */
  #[Hook('preprocess_paragraph__allu_decisions_search')]
  public function preprocessAlluDecisionsSearch(array &$variables): void {
    if ($proxy = $this->getPublicElasticProxy()) {
      $variables['#attached']['drupalSettings']['helfi_react_search']['elastic_proxy_url'] = $proxy->getAddress();
    }
  }

}
