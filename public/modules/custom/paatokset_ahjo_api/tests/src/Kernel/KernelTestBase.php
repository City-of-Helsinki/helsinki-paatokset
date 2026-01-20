<?php

declare(strict_types=1);

namespace Drupal\Tests\paatokset_ahjo_api\Kernel;

use Drupal\KernelTests\KernelTestBase as DrupalKernelTestBase;

/**
 * Base class for Ahjo api kernel tests.
 *
 * This base class installs Ahjo-related modules without installing
 * node configurations.
 *
 * Extending this instead of AhjoKernelTest (which includes node
 * configuration) makes the tests faster to run.
 */
abstract class KernelTestBase extends DrupalKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'helfi_api_base',
    'paatokset_ahjo_api',
    'json_field',
    'path_alias',
    'pathauto',
    'token',
    'migrate',
  ];

}
