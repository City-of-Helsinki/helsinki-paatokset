<?php

declare(strict_types=1);

namespace Drupal\paatokset_allu\Plugin\Deriver;

use Drupal\Component\Plugin\Derivative\DeriverBase;
use Drupal\paatokset_allu\DocumentType;

/**
 * Deriver for allu migration source plugin.
 */
class AlluSourcePlugin extends DeriverBase {

  /**
   * {@inheritdoc}
   */
  public function getDerivativeDefinitions($base_plugin_definition): array {
    $map = [
      DocumentType::DECISION->name,
      DocumentType::APPROVAL->name,
    ];

    foreach ($map as $document) {
      $this->derivatives[$document] = $base_plugin_definition;
      $this->derivatives[$document]['document'] = $document;
    }

    return $this->derivatives;
  }

}
