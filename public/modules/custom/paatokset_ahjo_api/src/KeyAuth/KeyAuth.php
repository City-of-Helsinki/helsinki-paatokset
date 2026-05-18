<?php

declare(strict_types=1);

namespace Drupal\paatokset_ahjo_api\KeyAuth;

use Drupal\key_auth\KeyAuth as KeyAuthBase;
use Symfony\Component\HttpFoundation\Request;

/**
 * Handles all functionality regarding key authentication.
 *
 * We override 'key_auth' module's service to provide support for
 * multiple detection parameter names.
 */
final class KeyAuth extends KeyAuthBase {

  /**
   * {@inheritdoc}
   */
  public function getKey(Request $request) : false|string {
    // AHJO send requests using Token header.
    if ($request->headers->has('Token')) {
      return $request->headers->get('Token') ?: FALSE;
    }
    return parent::getKey($request);
  }

}
