<?php

declare(strict_types=1);

namespace Drupal\paatokset_allu\Client;

use Drupal\paatokset_allu\ApprovalType;
use GuzzleHttp\ClientInterface;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Allu API client.
 */
readonly class Client {

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
    private ClientInterface $client,
    private TokenFactory $tokenFactory,
    private Settings $settings,
  ) {
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
