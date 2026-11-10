<?php

declare(strict_types=1);

namespace Drupal\Tests\paatokset_ahjo_api\Kernel\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\paatokset_ahjo_api\Entity\CaseBundle;
use Drupal\paatokset_ahjo_api\Service\CaseService;
use Drupal\Tests\paatokset_ahjo_api\Kernel\AhjoEntityKernelTestBase;

/**
 * Tests case service.
 */
class CaseServiceTest extends AhjoEntityKernelTestBase {

  /**
   * Tests policymaker loading for decisions.
   */
  public function testPolicymakersForDecisions(): void {
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

    $decisions = $case->getAllDecisions();
    $this->assertCount(3, $decisions);
    $policymakers = $sut->getPolicymakersForDecisions($decisions);
    $this->assertEmpty($policymakers);

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

    $policymakers = $sut->getPolicymakersForDecisions($decisions);
    $this->assertEqualsCanonicalizing(['1234', '4321'], array_map('strval', array_keys($policymakers)));
    $this->assertArrayHasKey('1234', $policymakers);
    $this->assertEquals('Test policymaker 1', $policymakers['1234']->getPolicymakerName());
  }

}
