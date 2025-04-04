<?php

declare(strict_types=1);

namespace Drupal\Tests\paatokset_search\Kernel\Controller;

use Drupal\KernelTests\KernelTestBase;
use Drupal\paatokset_search\Controller\SearchController;
use Drupal\paatokset_search\SearchManager;

/**
 * Tests search controller.
 *
 * @coversDefaultClass \Drupal\paatokset_search\Controller\SearchController
 */
class SearchControllerTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'paatokset_search',
  ];

  /**
   * Tests block render.
   *
   * @covers ::__construct
   * @covers ::decisions
   */
  public function testDecisions(): void {
    $controller = new SearchController($this->container->get(SearchManager::class));
    $build = $controller->decisions();

    $this->assertNotEmpty($build);
  }

}
