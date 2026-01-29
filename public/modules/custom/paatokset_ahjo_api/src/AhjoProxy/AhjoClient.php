<?php

declare(strict_types=1);

namespace Drupal\paatokset_ahjo_api\AhjoProxy;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\helfi_api_base\Environment\EnvironmentResolverInterface;
use Drupal\paatokset_ahjo_api\AhjoOpenId\AhjoOpenId;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Utils;

/**
 * Client to the actual Ahjo API.
 *
 * This does not go through the proxy layer. API token
 * and network configuration are required for this to work.
 *
 * This should be a drop-in replacement for AhjoProxyClient
 * if the environment has access to Ahjo API.
 *
 * @todo consider replacing AhjoProxyClient with this in production.
 */
readonly class AhjoClient extends AhjoProxyClient implements AhjoClientInterface {

  public function __construct(
    ClientInterface $client,
    EnvironmentResolverInterface $environmentResolver,
    ConfigFactoryInterface $configFactory,
    private AhjoOpenId $ahjoOpenId,
  ) {
    parent::__construct($client, $environmentResolver, $configFactory);
  }

  /**
   * {@inheritDoc}
   *
   * Override proxy implementation so that calls to go through the actual
   * Ahjo API instead of the proxy.
   */
  #[\Override]
  protected function makeRequest(string $endpoint, array $options = []): \stdClass {
    try {
      $env = $this->environmentResolver->getActiveEnvironmentName();
    }
    catch (\InvalidArgumentException $e) {
      throw new AhjoProxyException($e->getMessage(), previous: $e);
    }

    $token = $this->ahjoOpenId->checkAuthToken() ? $this->ahjoOpenId->getAuthToken() : FALSE;
    if (!$token) {
      throw new AhjoProxyException('Missing or invalid Ahjo token');
    }

    try {
      $response = $this->client
        ->request('GET', "$endpoint", NestedArray::mergeDeep($options, [
          'headers' => [
            'User-Agent' => "Ahjo $env",
            'Authorization' => "Bearer $token",
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
