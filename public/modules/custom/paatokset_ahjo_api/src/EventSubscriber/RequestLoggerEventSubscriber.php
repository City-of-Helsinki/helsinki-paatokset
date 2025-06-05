<?php

declare(strict_types=1);

namespace Drupal\paatokset_ahjo_api\EventSubscriber;

use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\migrate\Event\MigrateEvents;
use Drupal\migrate\Event\MigrateImportEvent;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class RequestLoggerEventSubscriber implements EventSubscriberInterface {

  /**
   * The constructor.
   * @param \Drupal\Core\Logger\LoggerChannelInterface $logger
   */
  public function __construct(
    #[Autowire(service: 'logger.channel.paatokset_ahjo_api')]
    private readonly LoggerChannelInterface $logger,
  ) {
  }

  /**
   * {@inheritDoc}
   */
  public static function getSubscribedEvents(): array {
    return [MigrateEvents::PRE_IMPORT => 'preImport'];
  }

  /**
   * Temporary logging to find out used ahjo api endpoints.
   *
   * @param \Drupal\migrate\Event\MigrateImportEvent $event
   *   The event.
   */
  public function preImport(MigrateImportEvent $event): void {
    $sourceUrls = $event->getMigration()->getSourceConfiguration()['urls'];

    foreach ($sourceUrls as $url) {
      if (
        !str_contains($url, 'ahjo-proxy') &&
        str_contains($url, 'ahjo')
      ) {
        $this->logger->debug('Sending Ahjo migration request to ' . $url);
        break;
      }
    }
  }

}
