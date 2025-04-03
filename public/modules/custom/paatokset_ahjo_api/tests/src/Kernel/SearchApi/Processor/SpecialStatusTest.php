<?php

declare(strict_types=1);

namespace Drupal\Tests\paatokset_ahjo_api\Kernel\SearchApi\Processor;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\paatokset_ahjo_api\Entity\Decision;
use Drupal\paatokset_ahjo_api\Plugin\search_api\processor\SpecialStatus;
use Drupal\search_api\Item\Field;
use Drupal\search_api\Utility\Utility;
use Drupal\Tests\paatokset_ahjo_api\Kernel\SearchApi\AhjoSearchApiKernelTestBase;

/**
 * Tests special_status search_api processor.
 */
class SpecialStatusTest extends AhjoSearchApiKernelTestBase {

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
    parent::setUp('special_status');

    $field = new Field($this->index, 'test_special_status');
    $field->setType('string');
    $field->setPropertyPath('special_status');
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

    $this->config('paatokset_helsinki_kanava.settings')
      ->set('city_council_id', '123')
      ->set('city_hall_id', '234')
      ->set('trustee_organization_type_id', '345')
      ->save();

    $decision = $storage->create([
      'type' => 'decision',
      'title' => 'Test decision',
      'field_policymaker_id' => '123',
    ]);

    $this->assertInstanceOf(Decision::class, $decision);

    $id = Utility::createCombinedId('entity:node', $decision->id() . ':' . $decision->language()->getId());
    $item = $this->container
      ->get('search_api.fields_helper')
      ->createItemFromObject($this->index, $decision->getTypedData(), $id);
    $fields = $item->getFields();

    $this->assertEquals([SpecialStatus::CITY_COUNCIL], $fields['test_special_status']->getValues());

    $decision->set('field_policymaker_id', '234');

    $item = $this->container
      ->get('search_api.fields_helper')
      ->createItemFromObject($this->index, $decision->getTypedData(), $id);
    $fields = $item->getFields();

    $this->assertEquals([SpecialStatus::CITY_HALL], $fields['test_special_status']->getValues());
  }

}
