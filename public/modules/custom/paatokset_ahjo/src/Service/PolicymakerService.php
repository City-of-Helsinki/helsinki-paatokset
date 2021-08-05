<?php

namespace Drupal\paatokset_ahjo\Service;

/**
 * Service class for policymaker-related data.
 *
 * @package Drupal\paatokset_ahjo\Services
 */
class PolicymakerService {

  /**
   * Transform org_type value to css class.
   *
   * @param string $org_type
   *   Org type value to transform.
   *
   * @return string
   *   Transformed css class
   */
  public static function transformType($org_type) {
    return str_replace('_', '-', $org_type);
  }

}
