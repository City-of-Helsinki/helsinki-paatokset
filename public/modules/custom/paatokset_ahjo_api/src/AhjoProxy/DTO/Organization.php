<?php

declare(strict_types=1);

namespace Drupal\paatokset_ahjo_api\AhjoProxy\DTO;

use Drupal\paatokset_ahjo_api\AhjoProxy\AhjoProxyException;

/**
 * Response from the organization endpoint.
 *
 * This represents a node in the organization tree.
 * Compared to organization DTO, this includes children and the parent.
 */
final readonly class Organization {

  /**
   * Constructs a new instance.
   *
   * @param OrganizationInfo $info
   *   Current organization.
   * @param OrganizationInfo|null $parent
   *   Parent organization (NULL for root organization).
   * @param array<OrganizationInfo> $children
   *   Child organizations.
   * @param array $sector
   *   Organization sector (?).
   */
  public function __construct(
    public OrganizationInfo $info,
    public ?OrganizationInfo $parent = NULL,
    public array $children = [],
    public array $sector = [],
  ) {
  }

  /**
   * Construct self from deserialized Ahjo response.
   *
   * @throws \Drupal\paatokset_ahjo_api\AhjoProxy\AhjoProxyException
   */
  public static function fromAhjoObject(\stdClass $object): self {
    try {
      $parentOrgs = $object->OrganizationLevelAbove->organizations ?? [];
      $parents = array_map(static fn ($item) => OrganizationInfo::fromAhjoObject($item), $parentOrgs);

      // As far as I know, an organization should never have
      // more than one parent. However, the data type is array.
      if (count($parents) > 1) {
        throw new AhjoProxyException('Organization has more than one parent.');
      }

      $childOrgs = $object->OrganizationLevelBelow->organizations ?? [];

      return new self(
        OrganizationInfo::fromAhjoObject($object),
        array_first($parents),
        array_map(static fn($item) => OrganizationInfo::fromAhjoObject($item), $childOrgs),
        (array) ($object->Sector ?? [])
      );
    }
    catch (\ValueError | \DateMalformedStringException $e) {
      throw new AhjoProxyException($e->getMessage(), previous: $e);
    }
  }

}
