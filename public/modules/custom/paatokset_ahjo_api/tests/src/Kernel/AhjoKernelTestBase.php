<?php

namespace Drupal\Tests\paatokset_ahjo_api\Kernel;

use Drupal\KernelTests\KernelTestBase;

/**
 * Base class for Ahjo api kernel tests.
 */
abstract class AhjoKernelTestBase extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'node',
    'paatokset_ahjo_api',
    'paatokset_ahjo_proxy',
    'migrate',
    'file',
    'text',
    'field',
    'datetime',
    'json_field',
    'paatokset_ahjo_openid',
  ];

  /**
   * {@inheritDoc}
   */
  public function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installSchema('node', 'node_access');

    // Install node types & fields.
    $this->installConfig('paatokset_ahjo_api');

    putenv('AHJO_PROXY_BASE_URL=https://ahjo-api-test');
  }

}