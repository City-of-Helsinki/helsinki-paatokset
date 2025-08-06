<?php

declare(strict_types=1);

namespace Drupal\Tests\paatokset_ahjo_api\Kernel\SideNav;

use Drupal\paatokset_ahjo_api\Plugin\Block\DocumentsBlock;
use Drupal\paatokset_policymakers\Service\PolicymakerService;

/**
 * Tests documents block.
 */
class DocumentsBlockTest extends AgendaBlockTestBase {

  /**
   * Tests block.
   */
  public function testBlock(): void {
    $documents = [
      '2025' => ['foobar'],
    ];

    $policymakerService = $this->prophesize(PolicymakerService::class);
    $policymakerService->setPolicyMakerByPath()->willReturn(TRUE);
    $policymakerService
      ->getApiMinutesFromElasticSearch(NULL, TRUE)
      ->willReturn($documents);

    $this->container->set('paatokset_policymakers', $policymakerService->reveal());

    $sut = DocumentsBlock::create($this->container, [], 'paatokset_minutes_of_discussion', ['provider' => 'paatokset_ahjo_api']);
    $build = $sut->build();

    $this->assertEquals(array_keys($documents), $build['#years']);
    $this->assertEquals($documents, $build['#list']);
    $this->assertEquals('documents', $build['#type']);
  }

}
