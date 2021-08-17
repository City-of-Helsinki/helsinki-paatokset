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
        '#markup' => '<h1>AHJO Open ID connector</h1>',
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
      $link_markup = '<p>To start authentication flow, go to <a href="' . $auth_url . '">' . $auth_url . '</a></p>';
    }
    else {
      $link_markup = '<p>Configuration needs to be added before authentication.</p>';
    }


    return [
      'title' => ['#markup' => '<h2>Authentication flow</h2>'],
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
    $refresh_url = Url::fromRoute('paatokset_ahjo_openid.refresh', [], ['absolute' => TRUE])->toString();
    if ($this->ahjoOpenId->checkAuthToken()) {
      $token_expiration = $this->ahjoOpenId->getAuthTokenExpiration();
      $token_status = '<p>Token is still valid until: ' . date(DATE_RFC2822, $token_expiration) . '</p>';
    }
    else {
      $token_status = '<p>Token has expired or has not been set.</p>';
    }
    return [
      'title' => ['#markup' => '<h2>Token info</h2>'],
      'status' => ['#markup' => $token_status],
      'link' => [
        '#markup' => '<p><a href="' . $refresh_url . '">Refresh token.</a></p>',
      ],
    ];
  }

  /**
   * Get section divider markup.
   *
   * @return array
   *   Markup array.
   */
  private function getDivider() {
    return [
      '#markup' => '<hr />',
    ];
  }

  /**
   * Authentication flow.
   */
  public function callback(Request $request) {
    $code = $request->query->get('code');
    if (!empty($code)) {
      $authentication_url = Url::fromRoute('paatokset_ahjo_openid.auth', ['code' => $code], ['absolute' => TRUE])->toString();
      print 'Continue to <a href="' . $authentication_url . '">' . $authentication_url . '</a>';
    }
    print '<br />';
    die('...');
  }

  /**
   * Get access and auth tokens.
   */
  public function auth($code = NULL) {
    $code = (string) $code;
    $data = $this->ahjoOpenId->getAuthAndRefreshTokens($code);

    if (isset($data->access_token) && isset($data->refresh_token)) {
      print 'Token successfully stored.<br /><br />';
      print 'ACCESS:<br />';
      print (string) $data->access_token;
      print '<br />';
      print 'EXPIRES IN:<br />';
      print (string) $data->expires_in;
      print '<br />';
      print 'REFRESH:<br />';
      print (string) $data->refresh_token;
      print '<br />';
    }
    else {
      print 'Unable to authenticate.';
      print '<br /><br />';
    }
    $index_url = Url::fromRoute('paatokset_ahjo_openid.index', [], ['absolute' => TRUE])->toString();

    print '<br /><br />';
    print '<a href="' . $index_url . '">Go back.</a>';
    die;
  }

  /**
   * Refresh Access token.
   */
  public function refresh() {
    $token = $this->ahjoOpenId->refreshAuthToken();
    if (!empty($token)) {
      print 'Access token has been refreshed and stored.<br /><br />';
      print (string) $token;
      print '<br />';
    }
    else {
      print 'Could not refresh access token.<br /><br />';
    }

    $index_url = Url::fromRoute('paatokset_ahjo_openid.index', [], ['absolute' => TRUE])->toString();

    print '<br /><br />';
    print '<a href="' . $index_url . '">Go back.</a>';
    die();
  }

  /**
   * Misc debug functionality.
   */
  public function debug() {
    $this->ahjoOpenId->introSpectToken();
    die('...');
  }

}
