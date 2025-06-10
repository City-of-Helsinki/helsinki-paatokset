<?php

declare(strict_types=1);

namespace Drupal\Tests\paatokset_ahjo_api\Kernel\Entity;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\paatokset_ahjo_api\Entity\CaseBundle;
use Drupal\paatokset_ahjo_api\Entity\Decision;
use Drupal\Tests\paatokset_ahjo_api\Kernel\AhjoKernelTestBase;

/**
 * Tests case bundle class.
 */
class CaseTest extends AhjoKernelTestBase {

  /**
   * Tests bundle class.
   */
  public function testBundleClass(): void {
    /** @var \Drupal\Core\Entity\EntityStorageInterface $storage */
    $storage = $this->container->get(EntityTypeManagerInterface::class)
      ->getStorage('node');

    $case = $storage->create([
      'type' => 'case',
      'status' => '1',
      'langcode' => 'en',
      'field_diary_number' => '123',
      'field_no_title_for_case' => '1',
    ]);

    $this->assertInstanceOf(CaseBundle::class, $case);

    $this->assertEquals('123', $case->getDiaryNumber());

    // Create a bunch of decisions.
    $middle = $storage->create([
      'type' => 'decision',
      'title' => 'Test decision 2',
      'status' => '1',
      'langcode' => 'en',
      'field_diary_number' => '123',
      'field_meeting_date' => '2018-01-01',
    ]);
    $first = $storage->create([
      'type' => 'decision',
      'title' => 'Test decision 1',
      'status' => '1',
      'langcode' => 'en',
      'field_diary_number' => '123',
      'field_meeting_date' => '2017-01-01',
    ]);
    $last = $storage->create([
      'type' => 'decision',
      'title' => 'Test decision 3',
      'status' => '1',
      'langcode' => 'en',
      'field_diary_number' => '123',
      'field_meeting_date' => '2019-01-01',
    ]);

    $first->save();
    $middle->save();
    $last->save();

    $this->assertInstanceOf(Decision::class, $first);
    $this->assertInstanceOf(Decision::class, $middle);
    $this->assertInstanceOf(Decision::class, $last);

    $this->assertEmpty($case->getPrevDecision($first));
    $this->assertEquals('Test decision 1', $case->getPrevDecision($middle)?->label());
    $this->assertEquals('Test decision 3', $case->getNextDecision($middle)?->label());
    $this->assertEmpty($case->getNextDecision($last));
  }

}
