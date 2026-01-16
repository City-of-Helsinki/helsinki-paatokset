<?php

namespace Drupal\Tests\paatokset_ahjo_api\Kernel;

/**
 * Base class for Ahjo api kernel tests that use node config.
 *
 * This base class installs Ahjo related nodes and their configurations.
 * For custom entity tests, extend KernelTestBase directly.
 */
abstract class AhjoEntityKernelTestBase extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'options',
    'node',
    'paatokset_policymakers',
    'file',
    'text',
    'field',
    'datetime',
    'json_field',
  ];

  /**
   * {@inheritDoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installEntitySchema('path_alias');
    $this->installSchema('node', 'node_access');

    // Install node types & fields.
    $this->installConfig('paatokset_ahjo_api');

    $this->installEntitySchema('ahjo_case');

    putenv('AHJO_PROXY_BASE_URL=https://ahjo-api-test');
  }

}
