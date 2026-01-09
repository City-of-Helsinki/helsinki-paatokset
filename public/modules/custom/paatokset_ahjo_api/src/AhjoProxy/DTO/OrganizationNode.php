<?php

declare(strict_types=1);

namespace Drupal\paatokset_ahjo_api\AhjoProxy\DTO;

/**
 * Response from the organization endpoint.
 *
 * This represents a node in the organization tree.
 * Compared to organization DTO, this includes children and the parent.
 */
final readonly class OrganizationNode {

  /**
   * Constructs a new instance.
   *
   * @param Organization $organization
   *   Current organization.
   * @param Organization|null $parent
   *   Parent organization (NULL for root organization).
   * @param array<Organization> $children
   *   Child organizations.
   * @param array $sector
   *   Organization sector (?).
   */
  public function __construct(
    public Organization $organization,
    public ?Organization $parent = NULL,
    public array $children = [],
    public array $sector = [],
  ) {
  }

}
