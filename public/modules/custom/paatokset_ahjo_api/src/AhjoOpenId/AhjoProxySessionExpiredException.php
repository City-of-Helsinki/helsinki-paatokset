<?php

declare(strict_types=1);

namespace Drupal\paatokset_ahjo_api\AhjoOpenId;

/**
 * Thrown when the user has no valid Helsinki AD access token.
 */
final class AhjoProxySessionExpiredException extends AhjoOpenIdException {
}
