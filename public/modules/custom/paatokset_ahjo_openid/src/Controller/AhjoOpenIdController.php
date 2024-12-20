<?php

declare(strict_types=1);

namespace Drupal\paatokset_ahjo_openid\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Url;
use Drupal\paatokset_ahjo_openid\AhjoOpenId;
use Drupal\paatokset_ahjo_openid\AhjoOpenIdException;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * AHJO Open ID page controller.
 *
 * @package Drupal\paatokset_ahjo_openid\Controller
 */
final class AhjoOpenIdController extends ControllerBase implements ContainerInjectionInterface {

  /**
   * Constructor.
   */
  public function __construct(private readonly AhjoOpenId $ahjoOpenId) {
  }

  /**
   * Create and inject.
   */
  public static function create(ContainerInterface $container) {
    return new self(
      $container->get('paatokset_ahjo_openid')
    );
  }

  /**
   * Debug API.
   */
  public function index() {
    return [
      'heading' => [
        '#markup' => '<h1>' . $this->t('AHJO Open ID connector') . '</h1>',
      ],
      'div_1' => $this->getDivider(),
      'auth_flow' => $this->getAuthFlowMarkup(),
      'div_2' => $this->getDivider(),
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
    $auth_url = $this->ahjoOpenId->getAuthUrl();

    if ($auth_url) {
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

    if ($this->ahjoOpenId->isConfigured()) {
      $refresh_url = Url::fromRoute('paatokset_ahjo_openid.refresh', [], ['absolute' => TRUE])->toString();
      $refresh_link = [
        '#markup' => '<p><a href="' . $refresh_url . '">' . $this->t('Refresh token.') . '</a></p>',
      ];
    }
    else {
      $refresh_link = [
        '#markup' => '<p>Refresh token is missing or module is not configured.</p>',
      ];
    }

    return [
      'title' => ['#markup' => '<h2>' . $this->t('Token info') . '</h2>'],
      'status' => ['#markup' => $token_status],
      'link' => $refresh_link,
      'token' => $token_link,
    ];
  }

  /**
   * Get section divider markup.
   *
   * @return array
   *   Markup array.
   */
  private function getDivider(): array {
    return [
      '#markup' => '<hr />',
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
    if (!empty($code)) {
      $authentication_url = Url::fromRoute('paatokset_ahjo_openid.auth', ['code' => $code], ['absolute' => TRUE])->toString();
      $auth_markup = '<p>' . $this->t('Continue to') . ' <a href="' . $authentication_url . '">' . $authentication_url . '</a></p>';
    }
    else {
      $auth_markup = '<p>' . $this->t('Authentication failed.') . '</p>';
    }

    return [
      '#markup' => $auth_markup,
    ];
  }

  /**
   * Get access and auth tokens.
   */
  public function auth($code = NULL): array {
    $code = (string) $code;
    try {
      $this->ahjoOpenId->getAuthAndRefreshTokens($code);
      $auth_response = $this->t('Token successfully stored!');
    }
    catch (AhjoOpenIdException $e) {
      $auth_response = $this->t('Unable to authenticate:') . ' ' . $e->getMessage();
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

  /**
   * Refresh Access token.
   */
  public function refresh(): array {
    try {
      $refresh_response = $this->ahjoOpenId->getAuthToken(TRUE);
    }
    catch (\Throwable $e) {
      $refresh_response = $e->getMessage();
    }

    $index_url = Url::fromRoute('paatokset_ahjo_openid.index', [], ['absolute' => TRUE])->toString();

    return [
      'response' => [
        '#markup' => '<p>' . $refresh_response . '</p>',
      ],
      'back_link' => [
        '#markup' => '<p><a href="' . $index_url . '">' . $this->t('Go back.') . '</a></p>',
      ],
    ];
  }

}
