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
   * Fetches cases in batches using the provided interval to split the date
   * range into smaller chunks.
   *
   * @param string $langcode
   *   Request langcode.
   * @param \DateTimeImmutable $handledAfter
   *   Lower bound for case handling date.
   * @param \DateTimeImmutable $handledBefore
   *   Upper bound for case handling date.
   * @param \DateInterval $interval
   *   Date interval for batching requests (e.g., P7D for 7 days).
   *   The date range will be split into chunks of this size.
   *
   * @return iterable<\Drupal\paatokset_ahjo_api\AhjoProxy\DTO\AhjoCase>
   *   Iterable of AhjoCase DTOs. This API returns incomplete cases.
   *   Use `::getCase()` to get all data.
   *
   * @throws \Drupal\paatokset_ahjo_api\AhjoProxy\AhjoProxyException
   *   If the API request fails or response is malformed.
   */
  public function getCases(string $langcode, \DateTimeImmutable $handledAfter, \DateTimeImmutable $handledBefore, \DateInterval $interval): iterable;

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
