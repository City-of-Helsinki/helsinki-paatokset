<?php

declare(strict_types=1);

namespace Drupal\paatokset_ahjo_api\Routing;

use Drupal\Core\ParamConverter\ParamConverterInterface;
use Drupal\paatokset_ahjo_api\Service\CaseService;
use Symfony\Component\Routing\Route;

/**
 * Converts decision native ID route parameter to decision node.
 *
 * Used by the case page ajax route.
 */
final class DecisionConverter implements ParamConverterInterface {

  public function __construct(
    private readonly CaseService $caseService,
  ) {
  }

  /**
   * {@inheritdoc}
   *
   * @param mixed $value
   *   Decision native ID.
   * @param mixed $definition
   *   Parameter definition.
   * @param string $name
   *   Parameter name.
   * @param array<string, mixed> $defaults
   *   Route defaults.
   */
  public function convert($value, $definition, $name, array $defaults) {
    if (empty($value)) {
      return NULL;
    }

    // Stricter check if case_id is present.
    return $this->caseService->getDecision($value, $defaults['case_id'] ?? NULL);
  }

  /**
   * {@inheritdoc}
   */
  public function applies($definition, $name, Route $route) {
    return ($definition['type'] ?? NULL) === 'decision';
  }

}
