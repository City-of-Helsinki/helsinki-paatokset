<?php

declare(strict_types=1);

namespace Drupal\paatokset_ahjo_api\Routing;

use Drupal\Core\ParamConverter\ParamConverterInterface;
use Drupal\paatokset_ahjo_api\Service\CaseService;
use Symfony\Component\Routing\Route;

/**
 * Base class for decision param converters.
 */
abstract class DecisionConverterBase implements ParamConverterInterface {

  /**
   * Apply param converter to params of this type.
   *
   * @var string
   */
  protected string $type;

  /**
   * Case service.
   *
   * @var \Drupal\paatokset_ahjo_api\Service\CaseService
   */
  protected CaseService $caseService;

  /**
   * Get CaseService.
   */
  protected function caseService(): CaseService {
    if (empty($this->caseService)) {
      $this->caseService = \Drupal::service('paatokset_ahjo_cases');
    }

    return $this->caseService;
  }

  /**
   * {@inheritdoc}
   */
  public function applies($definition, $name, Route $route) {
    return !empty($definition['type']) && $definition['type'] == $this->type;
  }

}
