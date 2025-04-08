<?php

declare(strict_types=1);

namespace Drupal\Tests\paatokset_ahjo_api\Kernel\SearchApi\Processor;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\search_api\Item\Field;
use Drupal\search_api\Utility\Utility;
use Drupal\Tests\paatokset_ahjo_api\Kernel\SearchApi\AhjoSearchApiKernelTestBase;

/**
 * Tests sector_id search_api processor.
 */
class SectorIdTest extends AhjoSearchApiKernelTestBase {

  /**
   * {@inheritdoc}
   */
  public function setUp($processor = NULL): void {
    parent::setUp('sector_id');

    $field = new Field($this->index, 'test_sector_id');
    $field->setType('string');
    $field->setPropertyPath('sector_id');
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

    $policymaker = $storage->create([
      'type' => 'policymaker',
      'status' => '1',
      'langcode' => 'en',
      'title' => 'Test policymaker',
      'field_dm_sector' => json_encode([
        'SectorID' => '123',
      ]),
    ]);
    $policymaker->save();

    $id = Utility::createCombinedId('entity:node', $policymaker->id() . ':' . $policymaker->language()->getId());
    $item = $this->container
      ->get('search_api.fields_helper')
      ->createItemFromObject($this->index, $policymaker->getTypedData(), $id);
    $fields = $item->getFields();

    $this->assertEquals(['123'], $fields['test_sector_id']->getValues());
  }

}
