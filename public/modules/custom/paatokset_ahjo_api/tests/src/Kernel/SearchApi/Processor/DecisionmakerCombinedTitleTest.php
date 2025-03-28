<?php

declare(strict_types=1);

namespace Drupal\Tests\paatokset_ahjo_api\Kernel\SearchApi\Processor;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\search_api\Item\Field;
use Drupal\search_api\Utility\Utility;
use Drupal\Tests\paatokset_ahjo_api\Kernel\SearchApi\AhjoSearchApiKernelTestBase;

/**
 * Tests decisionmaker_combined_title search_api processor.
 */
class DecisionmakerCombinedTitleTest extends AhjoSearchApiKernelTestBase {

  /**
   * {@inheritdoc}
   */
  public function setUp($processor = NULL): void {
    parent::setUp('decisionmaker_combined_title');

    $field = new Field($this->index, 'test_decisionmaker_combined_title');
    $field->setType('string');
    $field->setPropertyPath('decisionmaker_combined_title');
    $field->setDatasourceId('entity:node');
    $field->setLabel('Test field');

    $this->index->addField($field);
    $this->index->save();
  }

  /**
   * Tests combined title processor.
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
      'field_sector_name' => 'Test sector',
      'field_dm_org_name' => 'Test dm org name',
      'field_policymaker_id' => '123',
    ]);
    $policymaker->save();

    $id = Utility::createCombinedId('entity:node', $policymaker->id() . ':' . $policymaker->language()->getId());

    $item = $this->container
      ->get('search_api.fields_helper')
      ->createItemFromObject($this->index, $policymaker->getTypedData(), $id);

    // Extract field values and check the value.
    $fields = $item->getFields();

    $this->assertEquals(
      ['Test policymaker - Test sector - Test dm org name'],
      $fields['test_decisionmaker_combined_title']->getValues()
    );
  }

}
