<?php

namespace Drupal\paatokset_ahjo\Enum;

/**
 * Enum class for policymaker routes.
 */
class PolicymakerRoutes {
  const ORGANIZATION = [
    'Documents' => 'policymaker.documents',
  ];

  const TRUSTEE = [
    'Decisions' => 'policymaker.decisions',
  ];

  /**
   * Class constructor. Don't make instances of this class.
   */
  private function __construct() {}

  /**
   * Return all organization-specific routes.
   */
  public static function getOrganizationRoutes() {
    return self::ORGANIZATION;
  }

  /**
   * Return all trustee-specific routes.
   */
  public static function getTrusteeRoutes() {
    return self::TRUSTEE;
  }

}
