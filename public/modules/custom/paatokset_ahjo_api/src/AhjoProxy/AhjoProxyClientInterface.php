<?php

declare(strict_types=1);

namespace Drupal\paatokset_ahjo_api\AhjoProxy;

use Drupal\paatokset_ahjo_api\AhjoProxy\DTO\OrganizationNode;
use Drupal\paatokset_ahjo_api\AhjoProxy\DTO\Trustee;

/**
 * Ahjo proxy API client.
 */
interface AhjoProxyClientInterface {

  /**
   * Get trustee by ID.
   *
   * @param string $langcode
   *   Request langcode.
   * @param string $trusteeId
   *   Trustee ID.
   *
   * @throws \Drupal\paatokset_ahjo_api\AhjoProxy\AhjoProxyException
   */
  public function getTrustee(string $langcode, string $trusteeId): Trustee;

  /**
   * Get organization by ID.
   *
   * @param string $langcode
   *   Request langcode.
   * @param string $id
   *   Organization ID.
   *
   * @throws \Drupal\paatokset_ahjo_api\AhjoProxy\AhjoProxyException
   */
  public function getOrganization(string $langcode, string $id): OrganizationNode;

}
