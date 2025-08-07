<?php

declare(strict_types=1);

namespace Drupal\Tests\paatokset_ahjo_api\Kernel\SideNav;

use Drupal\KernelTests\KernelTestBase;

/**
 * Base class for testing agenda blocks.
 *
 * Agenda blocks are all blocks that use
 * the agendas-submenu.html.twig template.
 */
abstract class AgendaBlockTestBase extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'helfi_api_base',
    'paatokset_ahjo_api',
    'paatokset_policymakers',
    'path_alias',
    'pathauto',
    'token',
    'node',
    'user',
  ];

}
