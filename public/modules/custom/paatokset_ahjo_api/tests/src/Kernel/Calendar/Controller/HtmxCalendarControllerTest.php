<?php

declare(strict_types=1);

namespace Drupal\Tests\paatokset_ahjo_api\Kernel\Calendar\Controller;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\DependencyInjection\ClassResolverInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\paatokset_ahjo_api\Calendar\Controller\FrontpageCalendarController;
use Drupal\paatokset_ahjo_api\Calendar\Controller\PolicymakerCalendarController;
use Drupal\paatokset_ahjo_api\Entity\Policymaker;
use Drupal\Tests\helfi_api_base\Traits\ApiTestTrait;
use Drupal\Tests\paatokset_ahjo_api\Kernel\AhjoEntityKernelTestBase;
use Drupal\Tests\user\Traits\UserCreationTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests frontpage and policymaker calendar htmx controllers.
 */
#[Group('paatokset_ahjo_api')]
#[RunTestsInSeparateProcesses]
class HtmxCalendarControllerTest extends AhjoEntityKernelTestBase {

  use ApiTestTrait;
  use UserCreationTrait;

  /**
   * The policymaker node.
   */
  private Policymaker $policymaker;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->setUpCurrentUser(permissions: ['access content']);

    FieldStorageConfig::create([
      'field_name' => 'field_meetings_description',
      'entity_type' => 'node',
      'type' => 'text_long',
    ])->save();
    FieldConfig::create([
      'field_name' => 'field_meetings_description',
      'entity_type' => 'node',
      'bundle' => 'policymaker',
    ])->save();

    $storage = $this->container->get(EntityTypeManagerInterface::class)
      ->getStorage('node');

    $policymaker = $storage->create([
      'type' => 'policymaker',
      'status' => 1,
      'title' => 'Test policymaker',
      'field_policymaker_id' => 'dm-1',
      'field_organization_type' => 'Lautakunta',
    ]);
    $policymaker->save();
    assert($policymaker instanceof Policymaker);
    $this->policymaker = $policymaker;

    $meetings = [
      'past_meeting' => ['-2 days', 'pidetty', 'dm-1'],
      'upcoming_meeting' => ['+2 days', 'vahvistettu', 'dm-1'],
      'cancelled_meeting' => ['+3 days', 'peruttu', 'dm-1'],
      'other_meeting' => ['+4 days', 'vahvistettu', 'dm-2'],
    ];

    foreach ($meetings as $title => [$date, $status, $policymakerId]) {
      $storage->create([
        'type' => 'meeting',
        'status' => 1,
        'title' => $title,
        'field_meeting_id' => "meeting-$title",
        'field_meeting_dm_id' => $policymakerId,
        'field_meeting_date' => (new \DateTimeImmutable($date, new \DateTimeZone('UTC')))
          ->format(DateTimeItemInterface::DATETIME_STORAGE_FORMAT),
        'field_meeting_status' => $status,
      ])->save();
    }
  }

  /**
   * Tests the frontpage calendar.
   */
  public function testFrontpageCalendar(): void {
    $build = $this->container->get(ClassResolverInterface::class)
      ->getInstanceFromDefinition(FrontpageCalendarController::class)
      ->content();

    $this->assertSame('frontpage_calendar', $build['#theme']);

    $rows = $build['#meetings'];
    $this->assertSame(['upcoming_meeting', 'other_meeting'], array_map(static fn ($row) => $row['meeting']->label(), $rows));
    $this->assertSame('Test policymaker', $rows[0]['policymaker']?->label());
    $this->assertNull($rows[1]['policymaker']);

    $this->assertCacheability($build, 'node_list:meeting');
  }

  /**
   * Tests the policymaker calendar.
   */
  public function testPolicymakerCalendar(): void {
    $controller = $this->container->get(ClassResolverInterface::class)
      ->getInstanceFromDefinition(PolicymakerCalendarController::class);

    $build = $controller->content($this->policymaker);

    $this->assertSame('policymaker_calendar', $build['#theme']);
    $this->assertSame(
      ['upcoming_meeting', 'cancelled_meeting'],
      array_map(static fn ($meeting) => $meeting->label(), $build['#meetings']),
    );
    $this->assertCacheability($build, 'meeting_pm:dm-1');

    // Policymaker without upcoming meetings renders nothing,
    // but is still cached until the next day.
    $this->policymaker->set('field_policymaker_id', 'dm-3')->save();
    $build = $controller->content($this->policymaker);
    $this->assertArrayNotHasKey('#theme', $build);
    $this->assertCacheability($build, 'meeting_pm:dm-3');
  }

  /**
   * Asserts render array cacheability.
   *
   * @param array<string, mixed> $build
   *   The render array.
   * @param string $tag
   *   Expected cache tag.
   */
  private function assertCacheability(array $build, string $tag): void {
    $cache = CacheableMetadata::createFromRenderArray($build);
    $this->assertContains($tag, $cache->getCacheTags());
    $this->assertGreaterThan(0, $cache->getCacheMaxAge());
    $this->assertLessThanOrEqual(86400, $cache->getCacheMaxAge());
  }

}
