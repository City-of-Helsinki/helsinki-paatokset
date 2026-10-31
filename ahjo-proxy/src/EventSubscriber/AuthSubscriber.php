<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Auth\AccessTokenException;
use App\Auth\AccessTokenVerifier;
use App\Auth\ApiKeyVerifier;
use App\Auth\Admin;
use App\Auth\RequireAdmin;
use App\Auth\RequireApiKey;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Authenticates requests to controllers that require it.
 *
 * @see \App\Auth\RequireApiKey
 * @see \App\Auth\RequireAdmin
 */
final readonly class AuthSubscriber implements EventSubscriberInterface {

  public function __construct(
    private AccessTokenVerifier $accessTokenVerifier,
    private ApiKeyVerifier $apiKeyVerifier,
    private LoggerInterface $logger,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      KernelEvents::CONTROLLER => 'onController',
    ];
  }

  /**
   * Checks the credentials before the controller is called.
   */
  public function onController(ControllerEvent $event): void {
    // Includes both class and method attributes.
    $attributes = $event->getAttributes();
    $request = $event->getRequest();

    if (isset($attributes[RequireApiKey::class]) && !$this->apiKeyVerifier->verify($request->headers->get('api-key') ?? '')) {
      $event->setController(static fn () => new JsonResponse(['error' => 'Unauthorized.'], Response::HTTP_UNAUTHORIZED));
      return;
    }

    if (isset($attributes[RequireAdmin::class])) {
      try {
        $admin = $this->accessTokenVerifier->verify($request->headers->get('x-access-token') ?? '');
      }
      catch (AccessTokenException $e) {
        $this->logger->warning('Access token rejected: {message}', ['message' => $e->getMessage()]);
        $event->setController(static fn () => new JsonResponse(['error' => 'Unauthorized.'], Response::HTTP_UNAUTHORIZED));
        return;
      }

      $request->attributes->set(Admin::class, $admin);
    }
  }

}
