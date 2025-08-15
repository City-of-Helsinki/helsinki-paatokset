<?php

declare(strict_types=1);

namespace Drupal\Tests\paatokset_ahjo_api\Kernel\SideNav;

use Drupal\paatokset_ahjo_api\Plugin\Block\MinutesOfDiscussionBlock;
use Drupal\paatokset_policymakers\Service\PolicymakerService;

/**
 * Tests minutes of discussion block.
 */
class MinutesOfDiscussionBlockTest extends AgendaBlockTestBase {

  /**
   * Tests block.
   */
  public function testBlock(): void {
    $minutes = [
      '2025' => ['foobar'],
    ];

    $policymakerService = $this->prophesize(PolicymakerService::class);
    $policymakerService->setPolicyMakerByPath()->willReturn(TRUE);
    $policymakerService
      ->getMinutesOfDiscussion(NULL, TRUE)
      ->willReturn($minutes);

    $this->container->set('paatokset_policymakers', $policymakerService->reveal());

    $sut = MinutesOfDiscussionBlock::create($this->container, [], 'paatokset_minutes_of_discussion', ['provider' => 'paatokset_ahjo_api']);
    $build = $sut->build();

    $this->assertEquals(array_keys($minutes), $build['#years']);
    $this->assertEquals($minutes, $build['#list']);
    $this->assertEquals('documents', $build['#type']);
  }

}
