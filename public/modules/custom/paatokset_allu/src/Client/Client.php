<?php

declare(strict_types=1);

namespace Drupal\paatokset_allu\Client;

use Drupal\paatokset_allu\AlluException;
use Drupal\paatokset_allu\ApprovalType;
use Drupal\paatokset_allu\DecisionType;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Http\Message\ResponseInterface;

/**
 * Allu API client.
 */
class Client {

  /**
   * Constructs a new instance.
   *
   * @param \GuzzleHttp\ClientInterface $client
   *   The client.
   * @param \Drupal\paatokset_allu\Client\TokenFactory $tokenFactory
   *   The token factory.
   * @param \Drupal\paatokset_allu\Client\Settings $settings
   *   The settings.
   */
  public function __construct(
    private readonly ClientInterface $client,
    private readonly TokenFactory $tokenFactory,
    private readonly Settings $settings,
  ) {
  }

  /**
   * Search decisions.
   *
   * @param \Drupal\paatokset_allu\DecisionType $type
   *   Decision type.
   * @param \DateTimeImmutable $after
   *   Search document created after this time.
   * @param \DateTimeImmutable $before
   *   Search document created before this time.
   *
   * @return array
   *   Api response.
   *
   * @throws \Drupal\paatokset_allu\AlluException
   */
  public function decisions(DecisionType $type, \DateTimeImmutable $after, \DateTimeImmutable $before): array {
    try {
      $response = $this->client->request('POST', "{$this->settings->baseUrl}/external/v2/documents/applications/decisions/search", [
        'headers' => [
          'Authorization' => "Bearer {$this->tokenFactory->getToken()}",
        ],
        'json' => [
          'applicationType' => $type->value,
          'after' => $after->format(\DateTimeInterface::RFC3339),
          'before' => $before->format(\DateTimeInterface::RFC3339),
        ],
      ]);

      $content = $response->getBody()->getContents();

      return array_map(fn ($decision) => $decision + ['type' => $type->value], json_decode($content, TRUE));
    }
    catch (GuzzleException $e) {
      throw new AlluException($e->getMessage(), $e->getCode(), $e);
    }
  }

  /**
   * Search approvals.
   *
   * @param \Drupal\paatokset_allu\ApprovalType $type
   *   Decision type.
   * @param \DateTimeImmutable $after
   *   Search document created after this time.
   * @param \DateTimeImmutable $before
   *   Search document created before this time.
   *
   * @return array
   *   Api response.
   *
   * @throws \Drupal\paatokset_allu\AlluException
   */
  public function approvals(ApprovalType $type, \DateTimeImmutable $after, \DateTimeImmutable $before): array {
    try {
      $response = $this->client->request('POST', "{$this->settings->baseUrl}/external/v1/documents/applications/approval/{$type->value}/search", [
        'headers' => [
          'Authorization' => "Bearer {$this->tokenFactory->getToken()}",
        ],
        'json' => [
          'after' => $after->format(\DateTimeInterface::RFC3339),
          'before' => $before->format(\DateTimeInterface::RFC3339),
        ],
      ]);

      return json_decode($response->getBody()->getContents(), TRUE);

    }
    catch (GuzzleException $e) {
      throw new AlluException($e->getMessage(), $e->getCode(), $e);
    }
  }

  /**
   * Fetch decision document.
   *
   * @param string $id
   *   Application id.
   *
   * @return \Psr\Http\Message\ResponseInterface
   *   Successful API response with an unread PDF body stream.
   *
   * @throws \Drupal\paatokset_allu\AlluException
   */
  public function decisionDocument(string $id): ResponseInterface {
    try {
      return $this->client->request('GET', "{$this->settings->baseUrl}/external/v2/documents/applications/$id/decision", [
        'stream' => TRUE,
        'headers' => [
          'Authorization' => "Bearer {$this->tokenFactory->getToken()}",
        ],
      ]);
    }
    catch (GuzzleException $e) {
      throw new AlluException($e->getMessage(), $e->getCode(), $e);
    }
  }

  /**
   * Fetch approval document.
   *
   * @param string $id
   *   Application id.
   * @param \Drupal\paatokset_allu\ApprovalType $type
   *   Approval type.
   *
   * @return \Psr\Http\Message\ResponseInterface
   *   Successful API response with an unread PDF body stream.
   *
   * @throws \Drupal\paatokset_allu\AlluException
   */
  public function approvalDocument(string $id, ApprovalType $type): ResponseInterface {
    try {
      return $this->client->request('GET', "{$this->settings->baseUrl}/external/v2/documents/applications/$id/approval/{$type->name}", [
        'stream' => TRUE,
        'headers' => [
          'Authorization' => "Bearer {$this->tokenFactory->getToken()}",
        ],
      ]);
    }
    catch (GuzzleException $e) {
      throw new AlluException($e->getMessage(), $e->getCode(), $e);
    }
  }

}
