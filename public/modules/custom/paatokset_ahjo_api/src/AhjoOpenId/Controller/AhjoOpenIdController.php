<?php

declare(strict_types=1);

namespace Drupal\paatokset_ahjo_api\AhjoOpenId\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\DependencyInjection\AutowireTrait;
use Drupal\Core\Url;
use Drupal\Core\Utility\Error;
use Drupal\paatokset_ahjo_api\AhjoOpenId\AhjoOpenId;
use Drupal\paatokset_ahjo_api\AhjoOpenId\AhjoOpenIdException;
use Drupal\paatokset_ahjo_api\AhjoOpenId\Settings;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;
use Symfony\Component\HttpFoundation\Request;

/**
 * AHJO Open ID page controller.
 */
final class AhjoOpenIdController extends ControllerBase {

  use AutowireTrait;

  public function __construct(
    private readonly AhjoOpenId $ahjoOpenId,
    private readonly Settings $settings,
  ) {
  }

  /**
   * Debug API.
   */
  public function index(): array {
    return [
      'heading' => [
        '#markup' => '<h1>' . $this->t('AHJO Open ID connector') . '</h1>',
      ],
      'div_1' => ['#markup' => '<hr>'],
      'auth_flow' => $this->getAuthFlowMarkup(),
      'div_2' => ['#markup' => '<hr>'],
      'token_info' => $this->getTokenInfo(),
    ];
  }

  /**
   * Get auth token.
   */
  public function getToken(): array {
    $token = $this->ahjoOpenId->getAuthToken();
    return [
      'heading' => [
        '#markup' => '<h1>' . $this->t('Auth token') . '</h1>',
      ],
      'token' => [
        '#markup' => '<code>' . $token . '</code>',
      ],
    ];
  }

  /**
   * Get auth flow link.
   *
   * @return array
   *   Markup array.
   */
  private function getAuthFlowMarkup(): array {
    if ($auth_url = $this->settings->getAuthUrl()) {
      $link_markup = '<p>' . $this->t('To start authentication flow, go to:') . ' <a href="' . $auth_url . '">' . $auth_url . '</a></p>';
    }
    else {
      $link_markup = '<p>' . $this->t('Configuration needs to be added before authentication.') . '</p>';
    }

    return [
      'title' => ['#markup' => '<h2>' . $this->t('Authentication flow') . '</h2>'],
      'link' => [
        '#markup' => $link_markup,
      ],
    ];
  }

  /**
   * Get token info.
   *
   * @return array
   *   Markup array.
   */
  private function getTokenInfo(): array {
    if ($this->ahjoOpenId->checkAuthToken()) {
      $token_expiration = $this->ahjoOpenId->getAuthTokenExpiration();
      $token_status = '<p>' . $this->t('Token is still valid until %date', ['%date' => date(DATE_RFC2822, $token_expiration)]) . '</p>';
      $token_url = Url::fromRoute('paatokset_ahjo_openid.token', [], ['absolute' => TRUE])->toString();
      $token_link = [
        '#markup' => '<p><a href="' . $token_url . '">Show access token.</a></p>',
      ];
    }
    else {
      $token_status = '<p>' . $this->t('Token has expired or has not been set.') . '</p>';
      $token_link = NULL;
    }

    return [
      'title' => ['#markup' => '<h2>' . $this->t('Token info') . '</h2>'],
      'status' => ['#markup' => $token_status],
      'token' => $token_link,
    ];
  }

  /**
   * Authentication flow.
   *
   * @return array
   *   Markup array.
   */
  public function callback(Request $request): array {
    $code = $request->query->get('code');
    if (empty($code)) {
      throw new BadRequestException('Authentication code is missing.');
    }

    try {
      $this->ahjoOpenId->refreshAuthToken($code);
      $auth_response = $this->t('Token successfully stored!');
    }
    catch (AhjoOpenIdException $e) {
      Error::logException($this->getLogger('paatokset_ahjo_openid'), $e);

      $auth_response = $this->t('Unable to authenticate: @error', [
        '@error' => $e->getMessage(),
      ]);
    }

    $index_url = Url::fromRoute('paatokset_ahjo_openid.index', [], ['absolute' => TRUE])->toString();

    return [
      'response' => [
        '#markup' => '<p>' . $auth_response . '</p>',
      ],
      'back_link' => [
        '#markup' => '<p><a href="' . $index_url . '">' . $this->t('Go back.') . '</a></p>',
      ],
    ];
  }

}
