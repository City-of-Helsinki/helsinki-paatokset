<?php

declare(strict_types=1);

namespace Drupal\paatokset_ahjo_api\Entity;

/**
 * Enum for organization types.
 */
enum OrganizationType {

  /**
   * Policymaker roles for trustees (not decisionmaker organizations).
   */
  const array TRUSTEE_TYPES = ['Viranhaltija', 'Luottamushenkilö'];

  case TRUSTEE;
  case OFFICE_HOLDER;
  case COUNCIL;
  case CABINET;
  case BOARD;

  /**
   * True if organization type is considered trustee (?).
   */
  public function isTrustee(): bool {
    return in_array($this, [self::TRUSTEE, self::OFFICE_HOLDER]);
  }

  /**
   * Convert Ahjo Organization/Type field to TrusteeType enum.
   */
  public static function tryFromOrganizationType(string $type): ?self {
    return match (strtolower($type)) {
      'luottamushenkilö' => OrganizationType::TRUSTEE,
      'viranhaltija' => OrganizationType::OFFICE_HOLDER,
      'valtuusto' => OrganizationType::COUNCIL,
      'hallitus' => OrganizationType::CABINET,
      'lautakunta', 'toimi-/neuvottelukunta', 'jaosto' => OrganizationType::BOARD,
      // There might be other organization
      // types that we are not using.
      default => NULL,
    };
  }

}
