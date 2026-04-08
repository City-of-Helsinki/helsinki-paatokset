<?php

declare(strict_types=1);

namespace Drupal\paatokset_ahjo_api;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderBase;
use Drupal\key_auth\KeyAuthInterface;
use Drupal\paatokset_ahjo_api\EventSubscriber\CspMeetingsCalendarSubscriber;
use Drupal\helfi_platform_config\HelfiPlatformConfigServiceProvider;
use Drupal\paatokset_ahjo_api\KeyAuth\KeyAuth;

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

  /**
   * {@inheritdoc}
   */
  public function alter(ContainerBuilder $container) : void {
    // Override KeyAuth service with our own implementation.
    $services = [
      'key_auth',
      KeyAuthInterface::class,
    ];

    foreach ($services as $service) {
      if (!$container->hasDefinition($service)) {
        continue;
      }
      $definition = $container->getDefinition($service);
      $definition->setClass(KeyAuth::class);
    }
  }

}
