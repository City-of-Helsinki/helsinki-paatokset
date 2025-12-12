<?php

declare(strict_types=1);

namespace Drupal\paatokset_ahjo_api;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderBase;
use Drupal\paatokset_ahjo_api\EventSubscriber\CspMeetingsCalendarSubscriber;
use Drupal\helfi_platform_config\HelfiPlatformConfigServiceProvider;

/**
 * A service provider.
 */
final class PaatoksetAhjoApiServiceProvider extends ServiceProviderBase {

  /**
   * {@inheritdoc}
   */
  public function register(ContainerBuilder $container) : void {
    HelfiPlatformConfigServiceProvider::registerCspEventSubscribers($container, [
      CspMeetingsCalendarSubscriber::class,
    ]);
  }

}
