<?php

declare(strict_types=1);

namespace Drupal\paatokset_ahjo_api\KeyAuth;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\key_auth\KeyAuth as KeyAuthBase;
use Symfony\Component\HttpFoundation\Request;

/**
 * Handles all functionality regarding key authentication.
 *
 * We override 'key_auth' module's service to provide support for
 * multiple detection parameter names.
 */
final class KeyAuth extends KeyAuthBase {

  public function __construct(
    ConfigFactoryInterface $config_factory,
    EntityTypeManagerInterface $entity_type_manager,
    private readonly LoggerChannelInterface $logger,
  ) {
    parent::__construct($config_factory, $entity_type_manager);
  }

  /**
   * {@inheritdoc}
   */
  public function getKey(Request $request) : false|string {
    // AHJO sends requests using an 'Authorization: Token <token>' header.
    $authorization = $request->headers->get('Authorization', '');
    if (preg_match('/^Token (.+)$/i', $authorization, $matches)) {
      return $matches[1] ?: FALSE;
    }
    // Fall back to a plain 'Token: <token>' header.
    if ($request->headers->has('Token')) {
      // If these log entries don't appear, the fallback can be removed.
      $this->logger->info("Used 'Token' header fallback for key authentication.");
      return $request->headers->get('Token') ?: FALSE;
    }
    return parent::getKey($request);
  }

}
