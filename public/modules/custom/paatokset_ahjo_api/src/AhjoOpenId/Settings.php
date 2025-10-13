<?php

declare(strict_types=1);

namespace Drupal\paatokset_ahjo_api\AhjoOpenId;

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

  /**
   * Get authentication URL.
   *
   * @return string
   *   Auth URL.
   */
  public function getAuthUrl(): string {
    $this->assertValid();

    return sprintf('%s?%s', $this->authUrl, http_build_query([
      'client_id' => $this->clientId,
      'scope' => $this->openIdScope,
      'response_type' => 'code',
      'redirect_uri' => $this->callbackUrl,
    ]));
  }

  /**
   * Assert that all required settings are set.
   *
   * @throws \Drupal\paatokset_ahjo_api\AhjoOpenId\AhjoOpenIdException
   */
  public function assertValid(): void {
    $vars = get_object_vars($this);

    foreach ($vars as $key => $value) {
      if (empty($this->{$key})) {
        throw new AhjoOpenIdException("Ahjo Open Id is not configured");
      }
    }
  }

}
