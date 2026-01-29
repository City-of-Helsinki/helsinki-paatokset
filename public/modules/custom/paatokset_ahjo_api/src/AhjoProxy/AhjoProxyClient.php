<?php

declare(strict_types=1);

namespace Drupal\paatokset_ahjo_api\AhjoProxy;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\helfi_api_base\Environment\EnvironmentEnum;
use Drupal\helfi_api_base\Environment\EnvironmentResolverInterface;
use Drupal\helfi_api_base\Environment\Project;
use Drupal\paatokset_ahjo_api\AhjoProxy\DTO\AhjoCase;
use Drupal\paatokset_ahjo_api\AhjoProxy\DTO\AhjojulkaisuDocument;
use Drupal\paatokset_ahjo_api\AhjoProxy\DTO\Chairmanship;
use Drupal\paatokset_ahjo_api\AhjoProxy\DTO\Decisionmaker;
use Drupal\paatokset_ahjo_api\AhjoProxy\DTO\Organization;
use Drupal\paatokset_ahjo_api\AhjoProxy\DTO\Trustee;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Utils;

/**
 * Ahjo proxy API client.
 *
 * Requests made by this client are handled by AhjoProxyController
 * running on the production environment.
 *
 * @see \Drupal\paatokset_ahjo_api\AhjoProxy\Controller\AhjoProxyController
 */
readonly class AhjoProxyClient implements AhjoProxyClientInterface {

  public function __construct(
    protected ClientInterface $client,
    protected EnvironmentResolverInterface $environmentResolver,
    protected ConfigFactoryInterface $configFactory,
  ) {
  }

  /**
   * {@inheritDoc}
   */
  public function getTrustee(string $langcode, string $trusteeId): Trustee {
    $response = $this->makeRequest(sprintf('/agents/positionoftrust/%s', strtoupper($trusteeId)), [
      'query' => [
        'apireqlang' => $langcode,
      ],
    ]);

    return new Trustee(
      $response->ID,
      $response->Name,
      $response->CouncilGroup,
      array_map(AhjojulkaisuDocument::class . '::fromAhjoObject', $response->Initiatives),
      array_map(AhjojulkaisuDocument::class . '::fromAhjoObject', $response->Resolutions),
      array_map(static fn ($document) => new Chairmanship(
        $document->Position,
        $document->OrganizationName,
        $document->OrganizationID,
      ), $response->Chairmanships)
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getOrganization(string $langcode, string $id): Organization {
    $response = $this->makeRequest("/organization", [
      'query' => [
        'orgid' => $id,
        'apireqlang' => $langcode,
      ],
    ]);

    return Organization::fromAhjoObject($response);
  }

  /**
   * {@inheritdoc}
   */
  public function getCase(string $langcode, string $id): AhjoCase {
    $response = $this->makeRequest(sprintf('/cases/%s', strtoupper($id)), [
      'query' => [
        'apireqlang' => $langcode,
      ],
    ]);

    return AhjoCase::fromAhjoObject($response);
  }

  /**
   * {@inheritdoc}
   */
  public function getCases(string $langcode, \DateTimeImmutable $handledAfter, \DateTimeImmutable $handledBefore, \DateInterval $interval): iterable {
    foreach (new DateRangeIterator($handledAfter, $handledBefore, $interval) as [$current, $next]) {
      $response = $this->makeRequest('/cases', [
        'query' => [
          'apireqlang' => $langcode,
          'handledbefore' => $next->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z'),
          'handledsince' => $current->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z'),
          'size' => 1000,
        ],
      ]);

      if (!isset($response->cases) || !is_array($response->cases)) {
        throw new AhjoProxyException('Cases data not found in response.');
      }

      foreach ($response->cases as $caseObject) {
        yield AhjoCase::fromAhjoObject($caseObject);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getDecisionmaker(string $langcode, string $id): Decisionmaker {
    $response = $this->makeRequest('/agents/decisionmakers', [
      'query' => [
        'orgid' => strtoupper($id),
        'apireqlang' => $langcode,
      ],
    ]);

    if (!empty($response->organizations) && is_array($response->organizations)) {
      foreach ($response->organizations as $decisionMaker) {
        return new Decisionmaker(
          Organization::fromAhjoObject($decisionMaker),
          $decisionMaker->Composition ?? [],
        );
      }
    }

    throw new AhjoProxyException('Decisionmakers data not found in response.');
  }

  /**
   * {@inheritdoc}
   */
  public function getDecisionmakers(string $langcode, \DateTimeImmutable $changedAfter, \DateTimeImmutable $changedBefore, \DateInterval $interval): iterable {
    foreach (new DateRangeIterator($changedAfter, $changedBefore, $interval) as [$current, $next]) {
      $response = $this->makeRequest('/agents/decisionmakers', [
        'query' => [
          'apireqlang' => $langcode,
          'changedbefore' => $next->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z'),
          'changedsince' => $current->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z'),
        ],
      ]);

      if (!isset($response->decisionMakers) || !is_array($response->decisionMakers)) {
        throw new AhjoProxyException('Decisionmakers data not found in response.');
      }

      foreach ($response->decisionMakers as $decisionMaker) {
        yield new Decisionmaker(
          Organization::fromAhjoObject($decisionMaker->Organization),
          $decisionMaker->Composition ?? [],
        );
      }
    }
  }

  /**
   * Fetches resource from Ahjo proxy.
   *
   * @param string $endpoint
   *   Ahjo proxy endpoint.
   * @param array $options
   *   Request options.
   *
   * @throws \Drupal\paatokset_ahjo_api\AhjoProxy\AhjoProxyException
   */
  protected function makeRequest(string $endpoint, array $options = []): \stdClass {
    try {
      // Ahjo proxy is only active in production.
      $prod = $this->environmentResolver->getEnvironment(
        Project::PAATOKSET,
        EnvironmentEnum::Prod->value
      );

      $env = $this->environmentResolver->getActiveEnvironmentName();
    }
    catch (\InvalidArgumentException $e) {
      throw new AhjoProxyException($e->getMessage(), previous: $e);
    }

    $apiKey = $this->configFactory
      ->get('paatokset_ahjo_api.settings')
      ->get('proxy_api_key');

    try {
      $response = $this->client
        ->request('GET', sprintf('%s/ahjo-proxy/v2%s', $prod->getBaseUrl(), $endpoint), NestedArray::mergeDeep($options, [
          'headers' => [
            'User-Agent' => "Ahjo proxy $env",
            'api-key' => $apiKey,
          ],
        ]));

      $body = $response->getBody()->getContents();

      return Utils::jsonDecode($body);
    }
    catch (GuzzleException $e) {
      throw new AhjoProxyException($e->getMessage(), previous: $e);
    }
  }

}
