<?php

declare(strict_types=1);

namespace Drupal\Tests\paatokset_ahjo_api\Kernel;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\paatokset_ahjo_api\Entity\CaseBundle;
use Drupal\paatokset_ahjo_api\Service\CaseService;

/**
 * Tests case service.
 */
class CaseServiceTest extends AhjoEntityKernelTestBase {

  /**
   * Tests decision list generation.
   */
  public function testDecisionList(): void {
    $sut = $this->container->get(CaseService::class);

    /** @var \Drupal\Core\Entity\EntityStorageInterface $storage */
    $storage = $this->container->get(EntityTypeManagerInterface::class)
      ->getStorage('node');

    $case = $storage->create([
      'type' => 'case',
      'status' => '1',
      'langcode' => 'en',
      'field_diary_number' => '123',
    ]);

    $this->assertInstanceOf(CaseBundle::class, $case);

    $storage->create([
      'type' => 'decision',
      'title' => 'Test decision 0',
      'status' => '1',
      'langcode' => 'en',
      'field_diary_number' => '123',
      'field_policymaker_id' => '????',
      'field_meeting_date' => '2010-01-01',
    ])->save();
    $storage->create([
      'type' => 'decision',
      'title' => 'Test decision 1',
      'status' => '1',
      'langcode' => 'en',
      'field_diary_number' => '123',
      'field_policymaker_id' => '1234',
      'field_meeting_date' => '2017-01-01',
    ])->save();
    $storage->create([
      'type' => 'decision',
      'title' => 'Test decision 2',
      'status' => '1',
      'langcode' => 'en',
      'field_diary_number' => '123',
      'field_policymaker_id' => '4321',
      'field_meeting_date' => '2018-01-01',
    ])->save();

    $this->assertNotEmpty($sut->getDecisionsList($case));

    $storage->create([
      'type' => 'policymaker',
      'status' => '1',
      'langcode' => 'en',
      'title' => 'Test policymaker 1',
      'field_policymaker_id' => '1234',
    ])->save();
    $storage->create([
      'type' => 'policymaker',
      'status' => '1',
      'langcode' => 'en',
      'title' => 'Test policymaker 2',
      'field_policymaker_id' => '4321',
    ])->save();

    $list = $sut->getDecisionsList($case);
    $this->assertNotEmpty($list);
    foreach ($list as $item) {
      $this->assertStringStartsWith('Test decision', $item['title']);
    }
  }

}
