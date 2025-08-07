<?php

declare(strict_types=1);

namespace Drupal\Tests\paatokset_ahjo_api\Kernel\SideNav;

use Drupal\paatokset_ahjo_api\Plugin\Block\AgendasSubmenuBlock;
use Drupal\paatokset_policymakers\Service\PolicymakerService;

/**
 * Tests agendas submenu block.
 */
class AgendasSubmenuBlockTest extends AgendaBlockTestBase {

  /**
   * Tests block.
   */
  public function testBlock(): void {
    $agendas = [
      '2025' => ['foobar'],
    ];

    $policymakerService = $this->prophesize(PolicymakerService::class);
    $policymakerService->setPolicyMakerByPath()->willReturn(TRUE);
    $policymakerService
      ->getAgendasListFromElasticSearch(NULL, TRUE)
      ->willReturn($agendas);

    $this->container->set('paatokset_policymakers', $policymakerService->reveal());

    $sut = AgendasSubmenuBlock::create($this->container, [], 'agendas_submenu', ['provider' => 'paatokset_ahjo_api']);
    $build = $sut->build();

    $this->assertEquals(array_keys($agendas), $build['#years']);
    $this->assertEquals($agendas, $build['#list']);
    $this->assertEquals('decisions', $build['#type']);
  }

}
