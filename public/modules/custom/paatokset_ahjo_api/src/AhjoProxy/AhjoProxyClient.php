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
use Drupal\paatokset_ahjo_api\AhjoProxy\DTO\Organization;
use Drupal\paatokset_ahjo_api\AhjoProxy\DTO\OrganizationNode;
use Drupal\paatokset_ahjo_api\AhjoProxy\DTO\Trustee;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Utils;

/**
 * Ahjo proxy API client.
 */
readonly class AhjoProxyClient implements AhjoProxyClientInterface {

  public function __construct(
    private ClientInterface $client,
    private EnvironmentResolverInterface $environmentResolver,
    private ConfigFactoryInterface $configFactory,
  ) {
  }

  /**
   * {@inheritDoc}
   */
  public function getTrustee(string $langcode, string $trusteeId): Trustee {
    $response = $this->makeRequest("/trustees/single/$trusteeId", [
      'query' => [
        'apireqlang' => $langcode,
      ],
    ]);

    if (!$object = array_first($response->trustees)) {
      throw new AhjoProxyException('Trustee not found.');
    }

    return new Trustee(
      $object->ID,
      $object->Name,
      $object->CouncilGroup,
      array_map(AhjojulkaisuDocument::class . '::fromAhjoObject', $object->Initiatives),
      array_map(AhjojulkaisuDocument::class . '::fromAhjoObject', $object->Resolutions),
      array_map(static fn ($document) => new Chairmanship(
        $document->Position,
        $document->OrganizationName,
        $document->OrganizationID,
      ), $object->Chairmanships)
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getOrganization(string $langcode, string $id): OrganizationNode {
    $response = $this->makeRequest("/organization/single/$id", [
      'query' => [
        'apireqlang' => $langcode,
      ],
    ]);

    if (!$object = array_first($response->decisionMakers)?->Organization) {
      throw new AhjoProxyException('Organization not found.');
    }

    $parents = array_map(static fn ($item) => Organization::fromAhjoObject($item), $object->OrganizationLevelAbove->organizations);

    // As far as I know, an organization should never have
    // more than one parent. However, the data type is array.
    if (count($parents) > 1) {
      throw new AhjoProxyException('Organization has more than one parent.');
    }

    try {
      return new OrganizationNode(
        Organization::fromAhjoObject($object),
        array_first($parents),
        array_map(static fn($item) => Organization::fromAhjoObject($item), $object->OrganizationLevelBelow->organizations),
        (array) $object->Sector
      );
    }
    catch (\ValueError | \DateMalformedStringException $e) {
      throw new AhjoProxyException($e->getMessage(), previous: $e);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getCase(string $langcode, string $id): AhjoCase {
    $response = $this->makeRequest("/cases/single/$id", [
      'query' => [
        'apireqlang' => $langcode,
      ],
    ]);

    if (!$object = array_first($response->cases)) {
      throw new AhjoProxyException('Case not found.');
    }

    try {
      return AhjoCase::fromAhjoObject($object);
    }
    catch (\DateMalformedStringException $e) {
      throw new AhjoProxyException($e->getMessage(), previous: $e);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getCases(string $langcode, \DateTimeImmutable $handledAfter, \DateTimeImmutable $handledBefore, \DateInterval $interval): iterable {
    for ($current = $handledAfter; $current < $handledBefore; $current = $next) {
      $next = $current->add($interval);
      if ($next > $handledBefore) {
        $next = $handledBefore;
      }

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

      try {
        foreach ($response->cases as $caseObject) {
          yield AhjoCase::fromAhjoObject($caseObject);
        }
      }
      catch (\DateMalformedStringException $e) {
        throw new AhjoProxyException($e->getMessage(), previous: $e);
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
  private function makeRequest(string $endpoint, array $options = []): \stdClass {
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
        ->request('GET', sprintf('%s/ahjo-proxy%s', $prod->getBaseUrl(), $endpoint), NestedArray::mergeDeep($options, [
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
