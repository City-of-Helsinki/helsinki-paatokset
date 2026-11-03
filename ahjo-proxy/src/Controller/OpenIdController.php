<?php

declare(strict_types=1);

namespace App\Controller;

use App\AhjoOpenId\AhjoOpenId;
use App\AhjoOpenId\AhjoOpenIdException;
use App\AhjoOpenId\StateSigner;
use App\Auth\Admin;
use App\Auth\RequireAdmin;
use App\Auth\RequireApiKey;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Ahjo OpenID connector API.
 *
 * The OpenID flow must be completed manually once by a person with Ahjo
 * credentials. After that, the token is kept fresh with the refresh token.
 *
 * Drupal calls POST /openid with its api key and the access token of the
 * logged-in admin, and redirects the admin to the returned auth URL. Ahjo
 * redirects the browser back to the callback endpoint, which is authorized by
 * the signed state.
 *
 * @see \App\Command\RefreshTokenCommand
 * @see \App\Auth\AccessTokenVerifier
 * @see \App\AhjoOpenId\StateSigner
 */
final readonly class OpenIdController {

  public function __construct(
    private AhjoOpenId $ahjoOpenId,
    private StateSigner $stateSigner,
    private LoggerInterface $logger,
  ) {
  }

  /**
   * Gets the Ahjo auth token status.
   */
  #[Route('/openid', name: 'openid_status', methods: ['GET'])]
  #[RequireApiKey]
  public function status(): JsonResponse {
    return new JsonResponse([
      'expires' => $this->ahjoOpenId->getAuthTokenExpiration(),
    ], headers: ['Cache-Control' => 'no-store']);
  }

  /**
   * Starts the authentication flow.
   *
   * Redirects to the Ahjo authorize endpoint with a signed state.
   */
  #[Route('/openid', name: 'openid_start', methods: ['POST'])]
  #[RequireApiKey]
  #[RequireAdmin]
  public function start(Request $request): Response {
    try {
      $authUrl = $this->ahjoOpenId->getAuthUrl(
        $this->stateSigner->create(Admin::fromRequest($request)),
      );
    }
    catch (AhjoOpenIdException $e) {
      return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR, ['Cache-Control' => 'no-store']);
    }

    return new RedirectResponse($authUrl, headers: ['Cache-Control' => 'no-store']);
  }

  /**
   * OpenID callback. Exchanges the authorization code for an Ahjo auth token.
   */
  #[Route('/openid/callback', name: 'openid_callback', methods: ['GET'])]
  public function callback(Request $request): Response {
    try {
      // The browser arrives here from Ahjo. The request is authorized by
      // the signed state, which POST /openid hands out.
      $sub = $this->stateSigner->verify($request->query->getString('state'));
    }
    catch (AhjoOpenIdException $e) {
      $this->logger->warning('Ahjo OpenID callback rejected: {message}', ['message' => $e->getMessage()]);

      return self::page('Invalid or expired authentication request. Start the authentication flow again.', Response::HTTP_BAD_REQUEST);
    }

    $code = $request->query->getString('code');
    if ($code === '') {
      // Ahjo reports failed logins with error parameters instead of a code.
      $this->logger->error('Ahjo OpenID login by {sub} failed: {error}', [
        'sub' => $sub,
        'error' => $request->query->getString('error', 'missing code'),
      ]);

      return self::page('Ahjo login failed. Start the authentication flow again.', Response::HTTP_BAD_REQUEST);
    }

    try {
      $token = $this->ahjoOpenId->refreshAuthToken($code);
    }
    catch (AhjoOpenIdException $e) {
      $this->logger->error('Ahjo OpenID code exchange by {sub} failed: {message}', [
        'sub' => $sub,
        'message' => $e->getMessage(),
      ]);

      return self::page('Ahjo token request failed. Start the authentication flow again.', Response::HTTP_BAD_GATEWAY);
    }

    $this->logger->notice('Ahjo auth token stored by {sub}', ['sub' => $sub]);

    return self::page(sprintf('Ahjo auth token stored. The token is valid until %s.', date(DATE_ATOM, $token->expires)));
  }

  /**
   * Creates a minimal, uncacheable HTML page.
   */
  private static function page(string $message, int $status = Response::HTTP_OK): Response {
    $message = htmlspecialchars($message, ENT_QUOTES | ENT_HTML5);

    $html = <<<HTML
      <!doctype html>
      <html lang="en">
      <head><meta charset="utf-8"><title>Ahjo proxy authentication</title></head>
      <body><p>{$message}</p></body>
      </html>
      HTML;

    return new Response($html, $status, [
      'Content-Type' => 'text/html; charset=UTF-8',
      'Cache-Control' => 'no-store',
      'Referrer-Policy' => 'no-referrer',
      'Content-Security-Policy' => "default-src 'none'",
    ]);
  }

}
