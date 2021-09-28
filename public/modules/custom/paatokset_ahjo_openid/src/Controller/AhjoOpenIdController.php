<?php

declare(strict_types = 1);

namespace Drupal\paatokset_ahjo_openid\Controller;

use Drupal\Core\Url;
use Drupal\Core\Controller\ControllerBase;
use Drupal\paatokset_ahjo_openid\AhjoOpenId;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * AHJO Open ID page controller.
 *
 * @package Drupal\paatokset_ahjo_openid\Controller
 */
class AhjoOpenIdController extends ControllerBase {

  /**
   * Open ID Connector service.
   *
   * @var \Drupal\paatokset_ahjo_openid\AhjoOpenId
   */
  protected $ahjoOpenId;

  /**
   * Constructor.
   */
  public function __construct(AhjoOpenId $ahjo_open_id) {
    $this->ahjoOpenId = $ahjo_open_id;
  }

  /**
   * Create and inject.
   */
  public static function create(ContainerInterface $container) {
    return new static(
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
    }
    else {
      $token_status = '<p>' . $this->t('Token has expired or has not been set.') . '</p>';
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
    $data = $this->ahjoOpenId->getAuthAndRefreshTokens($code);

    if (isset($data->access_token) && isset($data->refresh_token)) {
      $auth_response = $this->t('Token successfully stored!');
    }
    else {
      $auth_response = $this->t('Unable to authenticate:') . ' ' . $data->error;
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
  public function refresh() {
    $token = $this->ahjoOpenId->refreshAuthToken();

    if (!empty($token)) {
      $refresh_response = $this->t('Access token has been refreshed and stored.');
    }
    else {
      $refresh_response = $this->t('Could not refresh access token.');
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

  /**
   * Misc debug functionality.
   */
  public function debug() {
    die('...');
  }

}
