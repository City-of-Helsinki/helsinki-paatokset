<?php

declare(strict_types=1);

namespace Drupal\paatokset_ahjo_api\AhjoOpenId;

/**
 * Thrown when the user has no valid Helsinki AD access token.
 *
 * Helsinki AD tokens expire sooner than the Drupal session. The user must log
 * in again to get a new token.
 *
 * @see \Drupal\paatokset_ahjo_api\AhjoOpenId\AhjoProxyOpenIdClient::reauthenticate()
 */
final class AhjoProxySessionExpiredException extends AhjoOpenIdException {
}
