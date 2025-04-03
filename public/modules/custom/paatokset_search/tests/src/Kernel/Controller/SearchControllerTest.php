<?php

declare(strict_types=1);

namespace Drupal\Tests\paatokset_search\Kernel\Controller;

use Drupal\KernelTests\KernelTestBase;
use Drupal\paatokset_search\Controller\SearchController;

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
   * @covers ::create
   * @covers ::decisions
   */
  public function testDecisions(): void {
    $controller = SearchController::create($this->container);
    $build = $controller->decisions();

    $this->assertNotEmpty($build);
  }

}
