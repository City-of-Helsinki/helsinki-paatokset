<?php

declare(strict_types=1);

namespace Drupal\paatokset_ahjo_api\AhjoProxy;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\helfi_api_base\Environment\EnvironmentEnum;
use Drupal\helfi_api_base\Environment\EnvironmentResolverInterface;
use Drupal\helfi_api_base\Environment\Project;
use Drupal\paatokset_ahjo_api\AhjoProxy\DTO\AhjojulkaisuDocument;
use Drupal\paatokset_ahjo_api\AhjoProxy\DTO\Chairmanship;
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
