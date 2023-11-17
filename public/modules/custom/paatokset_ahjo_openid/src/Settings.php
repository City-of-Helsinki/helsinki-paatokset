<?php

namespace Drupal\paatokset_ahjo_openid;

/**
 * DTO representing AhjoOpenId settings.
 */
final readonly class Settings {

  /**
   * Constructs a new instance.
   *
   * @param string $authUrl
   *   OpenID auth url.
   * @param string $tokenUrl
   *   OpenID token url.
   * @param string $callbackUrl
   *   OpenID callback url.
   * @param string $clientId
   *   OpenID client ID.
   * @param string $openIdScope
   *   OpenID client scopes.
   * @param string $clientSecret
   *   OpenID client secret.
   */
  public function __construct(
    public string $authUrl,
    public string $tokenUrl,
    public string $callbackUrl,
    public string $clientId,
    public string $openIdScope,
    public string $clientSecret,
  ) {
  }

}
