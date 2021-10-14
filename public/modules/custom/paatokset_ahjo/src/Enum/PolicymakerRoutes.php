<?php

namespace Drupal\paatokset_ahjo\Enum;

/**
 * Enum class for policymaker routes.
 */
class PolicymakerRoutes {
  const ORGANIZATION = [
    'documents' => 'policymaker.documents',
    'discussion_minutes' => 'policymaker.discussion_minutes',
  ];

  const TRUSTEE = [
    'decisions' => 'policymaker.decisions',
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
