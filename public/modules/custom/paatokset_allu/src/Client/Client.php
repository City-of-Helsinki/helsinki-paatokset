<?php

declare(strict_types=1);

namespace Drupal\paatokset_allu\Client;

use Drupal\paatokset_allu\AlluException;
use Drupal\paatokset_allu\ApprovalType;
use Drupal\paatokset_allu\DecisionType;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Utils;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Allu API client.
 */
class Client {

  /**
   * Cache control header for PDF documents.
   */
  public const DOCUMENT_CACHE_CONTROL = 'public, max-age=31536000, immutable';

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

      return array_map(fn ($decision) => $decision + ['type' => $type->value], Utils::jsonDecode($content, TRUE));
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

      return Utils::jsonDecode($response->getBody()->getContents(), TRUE);

    }
    catch (GuzzleException $e) {
      throw new AlluException($e->getMessage(), $e->getCode(), $e);
    }
  }

  /**
   * Get streamed response for decision document.
   *
   * @param string $id
   *   Application id.
   *
   * @return \Symfony\Component\HttpFoundation\StreamedResponse
   *   The response.
   */
  public function streamDecision(string $id): StreamedResponse {
    return new StreamedResponse($this->streamCallback("{$this->settings->baseUrl}/external/v2/documents/applications/$id/decision"), headers: [
      'Content-Type' => 'application/pdf',
      'Cache-Control' => self::DOCUMENT_CACHE_CONTROL,
    ]);
  }

  /**
   * Get streamed response for approval document.
   *
   * @param string $id
   *   Application id.
   * @param \Drupal\paatokset_allu\ApprovalType $type
   *   Approval type.
   *
   * @return \Symfony\Component\HttpFoundation\StreamedResponse
   *   The response.
   */
  public function streamApproval(string $id, ApprovalType $type): StreamedResponse {
    return new StreamedResponse($this->streamCallback("{$this->settings->baseUrl}/external/v2/documents/applications/$id/approval/{$type->name}"), headers: [
      'Content-Type' => 'application/pdf',
      'Cache-Control' => self::DOCUMENT_CACHE_CONTROL,
    ]);
  }

  /**
   * Get streamed response callback.
   *
   * @param string $url
   *   Document URL.
   *
   * @return callable
   *   Callback from streamed response.
   */
  private function streamCallback(string $url): callable {
    return function () use ($url): void {
      $resource = fopen('php://output', 'wb');

      try {
        $this->client->request('GET', $url, [
          'sink' => $resource,
          'headers' => [
            'Authorization' => "Bearer {$this->tokenFactory->getToken()}",
          ],
        ]);
      }
      finally {
        fclose($resource);
      }
    };
  }

}
