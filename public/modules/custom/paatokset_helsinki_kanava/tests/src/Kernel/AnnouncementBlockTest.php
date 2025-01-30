<?php

declare(strict_types=1);

namespace Drupal\Tests\paatokset_helsinki_kanava\Kernel;

use Drupal\block\Entity\Block;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Url;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\NodeInterface;
use Drupal\paatokset_ahjo_api\Service\MeetingService;
use Drupal\paatokset_helsinki_kanava\Plugin\Block\AnnouncementsBlock;
use Drupal\paatokset_policymakers\Service\PolicymakerService;
use Prophecy\Argument;
use Symfony\Component\DependencyInjection\Loader\Configurator\Traits\PropertyTrait;

/**
 * Tests live stream announcement.
 *
 * @group paatokset_helsinki_kanava
 */
class AnnouncementBlockTest extends KernelTestBase {

  use PropertyTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'paatokset_helsinki_kanava',
    'block',
  ];

  /**
   * {@inheritDoc}
   */
  public function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('block');
    $this->installEntitySchema('meeting_video');
  }

  /**
   * Tests that block max age works correctly.
   */
  public function testBlockMaxAge(): void {
    $now = strtotime("1 January 2025");

    $this
      ->config('paatokset_helsinki_kanava.settings')
      ->set('city_council_id', 'test_id')
      ->save();

    $policymakerNode = $this->prophesize(NodeInterface::class);
    $policymakerNode->toUrl(Argument::any(), Argument::any())
      ->willReturn(Url::fromUri('https://example.com'));

    $policymakerService = $this->prophesize(PolicymakerService::class);
    $policymakerService
      ->getPolicyMaker('test_id')
      ->willReturn($policymakerNode->reveal());

    $meetingService = $this->prophesize(MeetingService::class);
    $meetingService
      ->nextMeetingDate('test_id')
      // Note: meetings service returns timestamp in Europe/Helsinki timezone.
      ->willReturn(
        NULL,
        // Next meeting started 30 seconds ago.
        $now - 30,
        // Next meeting starts in 30 seconds in the future.
        $now + AnnouncementsBlock::MIN_CACHE_TTL + 30,
        // Next meeting starts after _a long time_.
        $now + 864000,
      );

    $time = $this->prophesize(TimeInterface::class);
    $time->getCurrentTime()->willReturn($now);

    $this->container->set('paatokset_policymakers', $policymakerService->reveal());
    $this->container->set('paatokset_ahjo_meetings', $meetingService->reveal());
    $this->container->set(TimeInterface::class, $time->reveal());

    $block = Block::create([
      'plugin' => 'paatokset_helsinki_kanava_announcements',
      'region' => 'footer',
      'id' => $this->randomMachineName(),
    ]);

    $plugin = $block->getPlugin();
    $this->assertCacheMaxAge(AnnouncementsBlock::MIN_CACHE_TTL, $plugin->build());

    // Live-stream is coming, cache until the live stream starts.
    $this->assertCacheMaxAge(AnnouncementsBlock::MIN_CACHE_TTL, $plugin->build());
    $this->assertCacheMaxAge(AnnouncementsBlock::MIN_CACHE_TTL + 30, $plugin->build());

    // Alert is not shown yet.
    $this->assertCacheMaxAge(864000 - AnnouncementsBlock::ALERT_OFFSET, $plugin->build());
  }

  /**
   * Asserts render array max cache age.
   */
  private function assertCacheMaxAge(int $expectedMaxAge, array $build): void {
    $this->assertEquals($expectedMaxAge, $build['#cache']['max-age']);
  }

}
