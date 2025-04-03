<?php

declare(strict_types=1);

namespace Drupal\Tests\paatokset_ahjo_api\Kernel\SearchApi\Processor;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\search_api\Item\Field;
use Drupal\search_api\Utility\Utility;
use Drupal\Tests\paatokset_ahjo_api\Kernel\SearchApi\AhjoSearchApiKernelTestBase;

/**
 * Tests decisionmaker_searchfield_data search_api processor.
 */
class DecisionmakerSearchfieldDataTest extends AhjoSearchApiKernelTestBase {

  /**
   * {@inheritdoc}
   */
  public function setUp($processor = NULL): void {
    parent::setUp('decisionmaker_searchfield_data');

    $field = new Field($this->index, 'test_decisionmaker_searchfield_data');
    $field->setType('string');
    $field->setPropertyPath('decisionmaker_searchfield_data');
    $field->setDatasourceId('entity:node');
    $field->setLabel('Test field');

    $this->index->addField($field);
    $this->index->save();
  }

  /**
   * Tests searchfield data processor.
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
      'field_ahjo_title' => 'Test ahjo title',
      'field_policymaker_id' => '123',
    ]);
    $policymaker->save();

    $id = Utility::createCombinedId('entity:node', $policymaker->id() . ':' . $policymaker->language()->getId());

    $item = $this->container
      ->get('search_api.fields_helper')
      ->createItemFromObject($this->index, $policymaker->getTypedData(), $id);

    // Extract field values and check the value.
    $fields = $item->getFields();
    $values = $fields['test_decisionmaker_searchfield_data']->getValues();

    $this->assertIsArray($values);
    $value = json_decode(reset($values), TRUE);

    $this->assertEquals(
      [
        'id' => '123',
        'sector' => [
          'fi' => 'Test sector',
          'en' => 'Test sector',
          'sv' => 'Test sector',
        ],
        'organization' => [
          'fi' => 'Test ahjo title',
          'en' => 'Test ahjo title',
          'sv' => 'Test ahjo title',
        ],
        'organization_above' => [
          'fi' => 'Test dm org name',
          'en' => 'Test dm org name',
          'sv' => 'Test dm org name',
        ],
      ],
      $value,
    );
  }

}
