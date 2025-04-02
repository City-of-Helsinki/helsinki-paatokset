<?php

declare(strict_types=1);

namespace Drupal\Tests\paatokset_ahjo_api\Kernel\SearchApi\Processor;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\search_api\Item\Field;
use Drupal\search_api\Utility\Utility;
use Drupal\Tests\paatokset_ahjo_api\Kernel\SearchApi\AhjoSearchApiKernelTestBase;

/**
 * Tests meeting_dm_data search_api processor.
 */
class MeetingDecisionmakerDataTest extends AhjoSearchApiKernelTestBase {

  /**
   * {@inheritdoc}
   */
  public function setUp($processor = NULL): void {
    parent::setUp('meeting_dm_data');

    $field = new Field($this->index, 'test_meeting_dm_data');
    $field->setType('string');
    $field->setPropertyPath('meeting_dm_data');
    $field->setDatasourceId('entity:node');
    $field->setLabel('Test field');

    $this->index->addField($field);
    $this->index->save();
  }

  /**
   * Tests color class processor.
   */
  public function testProcessor() {
    /** @var \Drupal\Core\Entity\EntityStorageInterface $storage */
    $storage = $this->container->get(EntityTypeManagerInterface::class)
      ->getStorage('node');

    $meeting = $storage->create([
      'type' => 'meeting',
      'title' => 'Test meeting',
      'field_meeting_dm_id' => '123',
    ]);

    $policymaker = $storage->create([
      'type' => 'policymaker',
      'status' => '1',
      'langcode' => 'en',
      'title' => 'Test policymaker',
      'field_policymaker_id' => '123',
      'field_organization_type' => 'test-type',
    ]);
    $policymaker->save();

    $id = Utility::createCombinedId('entity:node', $meeting->id() . ':' . $meeting->language()->getId());
    $item = $this->container
      ->get('search_api.fields_helper')
      ->createItemFromObject($this->index, $meeting->getTypedData(), $id);
    $fields = $item->getFields();

    $this->assertEquals([
      'title' => [
        'en' => 'Test policymaker',
      ],
      'type' => 'test-type',
    ], json_decode($fields['test_meeting_dm_data']->getValues()[0], TRUE));
  }

}
