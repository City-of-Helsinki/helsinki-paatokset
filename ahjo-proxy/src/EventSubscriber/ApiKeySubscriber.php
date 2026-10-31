<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Controller\ProxyController;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Requires a valid api key for proxy requests.
 *
 * The key is sent in the `api-key` header, like with Drupal key_auth
 * module that was used with the previous proxy versions.
 */
final readonly class ApiKeySubscriber implements EventSubscriberInterface {

  public function __construct(
    private string $apiKey,
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
   * Checks the api key before proxy controller is called.
   */
  public function onController(ControllerEvent $event): void {
    $controller = $event->getController();
    if (!is_array($controller) || !$controller[0] instanceof ProxyController) {
      return;
    }

    $key = $event->getRequest()->headers->get('api-key', '');

    // Deny everything if the api key is not configured.
    if ($this->apiKey === '' || !is_string($key) || !hash_equals($this->apiKey, $key)) {
      $event->setController(static fn () => new JsonResponse(['error' => 'Unauthorized.'], Response::HTTP_UNAUTHORIZED));
    }
  }

}
