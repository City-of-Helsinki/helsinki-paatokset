<?php

declare(strict_types=1);

namespace Drupal\Tests\paatokset_ahjo_api\Kernel\SearchApi\Processor;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\search_api\Item\Field;
use Drupal\search_api\Utility\Utility;
use Drupal\Tests\paatokset_ahjo_api\Kernel\SearchApi\AhjoSearchApiKernelTestBase;

/**
 * Tests meeting_phase search_api processor.
 */
class MeetingPhaseTest extends AhjoSearchApiKernelTestBase {

  /**
   * {@inheritDoc}
   */
  protected static $modules = [
    'paatokset_helsinki_kanava',
  ];

  /**
   * {@inheritdoc}
   */
  public function setUp($processor = NULL): void {
    parent::setUp('meeting_phase');

    $field = new Field($this->index, 'test_meeting_phase');
    $field->setType('string');
    $field->setPropertyPath('meeting_phase');
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
      'field_meeting_documents' => [
        json_encode(['Type' => 'unknown-ignored']),
        json_encode(['Type' => 'esityslista']),
      ],
    ]);

    $this->assertInstanceOf(ContentEntityInterface::class, $meeting);

    $id = Utility::createCombinedId('entity:node', $meeting->id() . ':' . $meeting->language()->getId());
    $item = $this->container
      ->get('search_api.fields_helper')
      ->createItemFromObject($this->index, $meeting->getTypedData(), $id);
    $fields = $item->getFields();

    $this->assertEquals(['agenda'], $fields['test_meeting_phase']->getValues());

    $meeting->set('field_meeting_documents', [json_encode(['Type' => 'pöytäkirja'])]);

    $id = Utility::createCombinedId('entity:node', $meeting->id() . ':' . $meeting->language()->getId());
    $item = $this->container
      ->get('search_api.fields_helper')
      ->createItemFromObject($this->index, $meeting->getTypedData(), $id);
    $fields = $item->getFields();

    $this->assertEquals(['minutes'], $fields['test_meeting_phase']->getValues());
  }

}
