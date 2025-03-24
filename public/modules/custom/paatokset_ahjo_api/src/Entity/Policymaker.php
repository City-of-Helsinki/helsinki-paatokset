<?php

declare(strict_types=1);

namespace Drupal\paatokset_ahjo_api\Entity;

use Drupal\node\Entity\Node;

/**
 * Bundle class for policymaker.
 */
class Policymaker extends Node {
  /**
   * Check if policymaker is currently active.
   *
   * @return bool
   *   Policymaker existing status.
   */
  public function isActive(): bool {
    return !$this->get('field_policymaker_existing')->isEmpty() && $this->get('field_policymaker_existing')->value;
  }

  /**
   * Gets policymaker color coding from node.
   *
   * @return string
   *   Color code for label.
   */
  public function getPolicymakerClass(): string {
    // First check overridden color code.
    if (!$this->get('field_organization_color_code')->isEmpty()) {
      return $this->get('field_organization_color_code')->value;
    }

    if ($this->get('field_city_council_division')->value) {
      return 'color-hopea';
    }

    // If type isn't set, return with no color.
    if ($this->get('field_organization_type')->isEmpty()) {
      return 'color-none';
    }

    // Use org type to determine color coding.
    return match (strtolower($this->get('field_organization_type')->value)) {
      'valtuusto' => 'color-kupari',
      'hallitus' => 'color-hopea',
      'viranhaltija' => 'color-suomenlinna',
      'luottamushenkilö' => 'color-engel',
      'lautakunta' => 'toimi-/neuvottelukunta',
      'jaosto' => 'color-sumu',
      default => 'color-none',
    };
  }
}
