<?php

namespace Drupal\paatokset_policymakers\Enum;

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

  const SUBROUTE = [
    'minutes' => 'policymaker.minutes',
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

  /**
   * Return all subroutes.
   */
  public static function getSubroutes() {
    return self::SUBROUTE;
  }

}
