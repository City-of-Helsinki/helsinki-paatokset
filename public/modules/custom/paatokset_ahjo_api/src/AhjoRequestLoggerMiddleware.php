<?php

declare(strict_types=1);

namespace Drupal\paatokset_ahjo_api;

use Drupal\Core\Logger\LoggerChannelInterface;
use Psr\Http\Message\RequestInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Guzzle middleware for logging Ahjo-requests.
 */
class AhjoRequestLoggerMiddleware {

  /**
   * The constructor.
   *
   * @param \Drupal\Core\Logger\LoggerChannelInterface $logger
   *   The logger channel.
   */
  public function __construct(
    #[Autowire(service: 'logger.channel.paatokset_ahjo_api')]
    private readonly LoggerChannelInterface $logger,
  ) {
  }

  /**
   * The invoke-method.
   *
   * @return callable
   *   The callable
   */
  public function __invoke(): callable {
    $logger = $this->logger;
    return function (callable $handler) use ($logger) {
      return function (RequestInterface $request, array $options) use ($handler, $logger) {
        $uri = $request->getUri();
        if (
          !str_contains($uri->getHost(), 'proxy') &&
          str_contains($uri->getHost(), 'ahjo')
        ) {
          $logger->debug('Sending Ahjo migration request to ' . $uri);
        }
        return $handler($request, $options);
      };
    };
  }

}
