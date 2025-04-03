<?php

declare(strict_types=1);

namespace Drupal\Tests\paatokset_ahjo_api\Kernel\SearchApi\Processor;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\node\NodeInterface;
use Drupal\search_api\Item\Field;
use Drupal\search_api\Utility\Utility;
use Drupal\Tests\paatokset_ahjo_api\Kernel\SearchApi\AhjoSearchApiKernelTestBase;

/**
 * Tests color_class search_api processor.
 */
class ColorClassTest extends AhjoSearchApiKernelTestBase {

  /**
   * {@inheritdoc}
   */
  public function setUp($processor = NULL): void {
    parent::setUp('color_class');

    $field = new Field($this->index, 'test_color_class');
    $field->setType('string');
    $field->setPropertyPath('color_class');
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

    $decision = $storage->create([
      'type' => 'decision',
      'title' => 'Test decision',
      'field_policymaker_id' => '123',
    ]);
    $decision->save();

    $policymaker = $storage->create([
      'type' => 'policymaker',
      'status' => '1',
      'langcode' => 'en',
      'title' => 'Test policymaker',
      'field_policymaker_id' => '123',
      'field_organization_color_code' => 'test-color',
    ]);
    $policymaker->save();

    $this->assertColorClassField('test-color', 'test_color_class', $decision);
    $this->assertColorClassField('test-color', 'test_color_class', $policymaker);
  }

  /**
   * Asserts search api index color class field.
   */
  private function assertColorClassField(string $expected, string $field, NodeInterface $entity): void {
    $id = Utility::createCombinedId('entity:node', $entity->id() . ':' . $entity->language()->getId());
    $item = $this->container
      ->get('search_api.fields_helper')
      ->createItemFromObject($this->index, $entity->getTypedData(), $id);

    // Extract field values and check the value.
    $fields = $item->getFields();

    $this->assertEquals([$expected], $fields[$field]->getValues());
  }

}
