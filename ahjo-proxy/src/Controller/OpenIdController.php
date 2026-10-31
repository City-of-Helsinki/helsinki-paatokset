<?php

declare(strict_types=1);

namespace App\Controller;

use App\AhjoOpenId\AhjoOpenId;
use App\AhjoOpenId\AhjoOpenIdException;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Ahjo OpenID connector pages.
 *
 * The OpenID flow must be completed manually once by a person with Ahjo
 * credentials. After that, the token is kept fresh with the refresh token.
 *
 * @see \App\Command\RefreshTokenCommand
 */
final readonly class OpenIdController {

  public function __construct(
    private AhjoOpenId $ahjoOpenId,
    private UrlGeneratorInterface $urlGenerator,
    private LoggerInterface $logger,
  ) {
  }

  /**
   * Shows token status and the link that starts the authentication flow.
   */
  #[Route('/openid', name: 'openid_index', methods: ['GET'])]
  public function index(): Response {
    try {
      $authUrl = $this->ahjoOpenId->getAuthUrl();
      $authFlow = sprintf('<p>To start authentication flow, go to: <a href="%s">%s</a></p>', self::escape($authUrl), self::escape($authUrl));
    }
    catch (AhjoOpenIdException $e) {
      $authFlow = sprintf('<p>Configuration needs to be added before authentication: %s</p>', self::escape($e->getMessage()));
    }

    $expires = $this->ahjoOpenId->getAuthTokenExpiration();
    $tokenStatus = match (TRUE) {
      $expires === NULL => 'Token has not been set.',
      $expires < time() => sprintf('Token expired at %s.', date(DATE_RFC2822, $expires)),
      default => sprintf('Token is valid until %s.', date(DATE_RFC2822, $expires)),
    };

    return self::page('Ahjo OpenID connector', <<<HTML
      <h2>Authentication flow</h2>
      $authFlow
      <h2>Token info</h2>
      <p>$tokenStatus</p>
      HTML);
  }

  /**
   * Handles the OpenID callback.
   */
  #[Route('/openid/callback', name: 'openid_callback', methods: ['GET'])]
  public function callback(Request $request): Response {
    $code = $request->query->getString('code');
    $state = $request->query->getString('state');

    if ($code === '' || !$this->ahjoOpenId->validateState($state)) {
      return self::page('Ahjo OpenID connector', '<p>Invalid authentication request. Start the authentication flow again.</p>', Response::HTTP_BAD_REQUEST);
    }

    $status = Response::HTTP_OK;
    try {
      $this->ahjoOpenId->refreshAuthToken($code);
      $message = 'Token successfully stored!';
    }
    catch (AhjoOpenIdException $e) {
      $this->logger->error('Ahjo OpenID callback failed: {message}', ['message' => $e->getMessage()]);
      $message = sprintf('Unable to authenticate: %s', self::escape($e->getMessage()));
      $status = Response::HTTP_BAD_GATEWAY;
    }

    $indexUrl = self::escape($this->urlGenerator->generate('openid_index'));

    return self::page('Ahjo OpenID connector', "<p>$message</p><p><a href=\"$indexUrl\">Go back.</a></p>", $status);
  }

  /**
   * Builds a minimal HTML page.
   */
  private static function page(string $title, string $body, int $status = Response::HTTP_OK): Response {
    $title = self::escape($title);

    return new Response(<<<HTML
      <!DOCTYPE html>
      <html lang="en">
      <head><meta charset="utf-8"><meta name="robots" content="noindex"><title>$title</title></head>
      <body><h1>$title</h1>$body</body>
      </html>
      HTML, $status, [
        'Content-Type' => 'text/html; charset=UTF-8',
        'Cache-Control' => 'no-store',
      ]);
  }

  /**
   * Escapes value for HTML output.
   */
  private static function escape(string $value): string {
    return htmlspecialchars($value, ENT_QUOTES | ENT_HTML5);
  }

}
