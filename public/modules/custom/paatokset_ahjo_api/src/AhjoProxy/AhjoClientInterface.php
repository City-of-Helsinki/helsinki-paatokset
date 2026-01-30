<?php

declare(strict_types=1);

namespace Drupal\paatokset_ahjo_api\AhjoProxy;

use Drupal\paatokset_ahjo_api\AhjoProxy\DTO\AhjoCase;
use Drupal\paatokset_ahjo_api\AhjoProxy\DTO\Decisionmaker;
use Drupal\paatokset_ahjo_api\AhjoProxy\DTO\Organization;
use Drupal\paatokset_ahjo_api\AhjoProxy\DTO\Trustee;

/**
 * Ahjo proxy API client.
 */
interface AhjoClientInterface {

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
  public function getOrganization(string $langcode, string $id): Organization;

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

  /**
   * Get single decisionmaker.
   *
   * @param string $langcode
   *   Request langcode.
   * @param string $id
   *   Decisionmaker ID.
   *
   * @throws \Drupal\paatokset_ahjo_api\AhjoProxy\AhjoProxyException
   */
  public function getDecisionmaker(string $langcode, string $id): Decisionmaker;

  /**
   * Get decisionmakers.
   *
   * Fetches decisionmaking organizations in batches using the provided
   * interval to split the date range into smaller chunks.
   *
   * @param string $langcode
   *   Request langcode.
   * @param \DateTimeImmutable $changedAfter
   *   Lower bound for change date.
   * @param \DateTimeImmutable $changedBefore
   *   Upper bound for change date.
   * @param \DateInterval $interval
   *   Date interval for batching requests (e.g., P7D for 7 days).
   *   The date range will be split into chunks of this size.
   *
   * @return iterable<\Drupal\paatokset_ahjo_api\AhjoProxy\DTO\Decisionmaker>
   *   Iterable of Decisionmaker DTOs.
   *
   * @throws \Drupal\paatokset_ahjo_api\AhjoProxy\AhjoProxyException
   */
  public function getDecisionmakers(string $langcode, \DateTimeImmutable $changedAfter, \DateTimeImmutable $changedBefore, \DateInterval $interval): iterable;

}
