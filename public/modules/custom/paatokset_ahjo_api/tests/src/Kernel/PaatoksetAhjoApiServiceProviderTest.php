<?php

declare(strict_types=1);

namespace Drupal\Tests\paatokset_ahjo_api\Kernel;

use Drupal\paatokset_ahjo_api\EventSubscriber\CspMeetingsCalendarSubscriber;

/**
 * Tests Paatokset Ahjo API service provider.
 *
 * @coversDefaultClass \Drupal\paatokset_ahjo_api\PaatoksetAhjoApiServiceProvider
 */
class PaatoksetAhjoApiServiceProviderTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'csp',
  ];

  /**
   * Tests service definition registration.
   *
   * @covers ::register
   */
  public function testServiceDefinitionRegistration(): void {
    $this->assertInstanceOf(CspMeetingsCalendarSubscriber::class, $this->container->get(CspMeetingsCalendarSubscriber::class));
  }

}
