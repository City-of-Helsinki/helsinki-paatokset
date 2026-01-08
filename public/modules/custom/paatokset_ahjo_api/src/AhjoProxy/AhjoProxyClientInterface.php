<?php

declare(strict_types=1);

namespace Drupal\paatokset_ahjo_api\AhjoProxy;

use Drupal\paatokset_ahjo_api\AhjoProxy\DTO\AhjoCase;
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

  /**
   * Get multiple cases.
   *
   * @param \DateTimeImmutable $handledAfter
   *   Lower bound for case handling date.
   * @param \DateTimeImmutable $handledBefore
   *   Upper bound for case handling date.
   * @param int $count
   *   Maximum number of cases to retrieve.
   *
   * @return \Drupal\paatokset_ahjo_api\AhjoProxy\DTO\AhjoCase[]
   *   Array of AhjoCase DTOs. This API returns incomplete cases.
   *   Use `::getCase()` to get all data.
   *
   * @throws \Drupal\paatokset_ahjo_api\AhjoProxy\AhjoProxyException
   *   If the API request fails or response is malformed.
   */
  public function getCases(\DateTimeImmutable $handledAfter, \DateTimeImmutable $handledBefore, int $count = 1000): array;

  /**
   * Get case by ID.
   *
   * @param string $langcode
   *   Request langcode.
   * @param string $id
   *   Case ID.
   *
   * @throws \Drupal\paatokset_ahjo_api\AhjoProxy\AhjoProxyException
   */
  public function getCase(string $langcode, string $id): AhjoCase;

}
