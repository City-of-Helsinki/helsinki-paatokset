<?php

declare(strict_types=1);

namespace Drupal\paatokset\EventSubscriber;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\EventSubscriber\HttpExceptionSubscriberBase;
use Drupal\Core\Routing\TrustedRedirectResponse;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\paatokset\Entity\Article;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;

/**
 * Listens to http exceptions.
 *
 * @see \Drupal\helfi_rekry_content\EventSubscriber\JobListingRedirectSubscriber
 */
class ExceptionSubscriber extends HttpExceptionSubscriberBase {

  /**
   * Constructs a new instance.
   */
  public function __construct(
    private readonly AccountInterface $currentUser,
    private readonly ConfigFactoryInterface $configFactory,
  ) {
  }

  /**
   * Redirects on 403 Access Denied kernel exceptions.
   *
   * @param \Symfony\Component\HttpKernel\Event\ExceptionEvent $event
   *   The Event to process.
   */
  public function on403(ExceptionEvent $event): void {
    $request = $event->getRequest();
    $node = $request->attributes->get('node');
    if (!$node instanceof Article) {
      return;
    }

    if ($node->access('view', $this->currentUser)) {
      return;
    }

    $paatoksetSettings = $this->configFactory->get('paatokset.settings');
    $nid = $paatoksetSettings->get('redirect_403_page');
    if (!$nid) {
      return;
    }

    $url = Url::fromRoute('entity.node.canonical', [
      'node' => $nid,
    ])->toString();

    $cache = new CacheableMetadata();
    $cache->addCacheableDependency($node);
    $cache->addCacheableDependency($paatoksetSettings);
    $cache->addCacheContexts(['user.permissions']);

    // Redirect user to configured page with 410 (Gone) status code.
    $response = new TrustedRedirectResponse($url);
    $response->addCacheableDependency($cache);
    $response->setStatusCode(410);
    $event->setResponse($response);
  }

  /**
   * {@inheritdoc}
   */
  protected function getHandledFormats(): array {
    return ['html'];
  }

}
