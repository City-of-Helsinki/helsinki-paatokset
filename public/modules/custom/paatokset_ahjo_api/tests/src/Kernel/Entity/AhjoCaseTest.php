<?php

declare(strict_types=1);

namespace Drupal\Tests\paatokset_ahjo_api\Kernel\Entity;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\node\Entity\Node;
use Drupal\paatokset_ahjo_api\Entity\AhjoCase;
use Drupal\paatokset_ahjo_api\Entity\ConfidentialityInterface;
use Drupal\paatokset_ahjo_api\Entity\Decision;
use Drupal\paatokset_ahjo_api\Entity\TopCategory;
use Drupal\Tests\paatokset_ahjo_api\Kernel\AhjoEntityKernelTestBase;

/**
 * Tests ahjo case entity.
 */
class AhjoCaseTest extends AhjoEntityKernelTestBase {

  /**
   * Tests bundle class.
   */
  public function testEntity(): void {
    /** @var \Drupal\Core\Entity\EntityStorageInterface $storage */
    $storage = $this->container->get(EntityTypeManagerInterface::class)
      ->getStorage('ahjo_case');

    $case = $storage->create([
      'id' => '123',
      'status' => '1',
      'langcode' => 'en',
      'classification_code' => '11 01 05',
    ]);
    $case->save();

    $this->assertInstanceOf(ConfidentialityInterface::class, $case);
    $this->assertInstanceOf(AhjoCase::class, $case);

    $this->assertEquals('123', $case->id());

    // Create a bunch of decisions.
    $middle = Node::create([
      'type' => 'decision',
      'title' => 'Test decision 2',
      'status' => '1',
      'langcode' => 'en',
      'field_diary_number' => '123',
      'field_meeting_date' => '2018-01-01',
    ]);
    $first = Node::create([
      'type' => 'decision',
      'title' => 'Test decision 1',
      'status' => '1',
      'langcode' => 'en',
      'field_diary_number' => '123',
      'field_meeting_date' => '2017-01-01',
    ]);
    $last = Node::create([
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

    $this->assertFalse($case->isConfidential());
    $case->set('security_reasons', 'foo');
    $this->assertTrue($case->isConfidential());
    $this->assertEquals($case->getConfidentialityReason(), 'foo');

    $this->assertEquals($case->getTopCategory(), TopCategory::EnvironmentalMatters);
  }

}
