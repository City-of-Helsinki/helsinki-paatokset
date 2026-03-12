<?php

declare(strict_types=1);

namespace Drupal\Tests\paatokset_ahjo_api\Kernel\SearchApi\Processor;

use Drupal\paatokset_ahjo_api\Entity\Decision;
use Drupal\paatokset_ahjo_api\Plugin\search_api\processor\SpecialStatus;
use Drupal\paatokset_ahjo_api\Service\PolicymakerService;
use Drupal\search_api\Item\Field;
use Drupal\search_api\Utility\Utility;
use Drupal\Tests\paatokset_ahjo_api\Kernel\SearchApi\AhjoSearchApiKernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests special_status search_api processor.
 */
#[RunTestsInSeparateProcesses]
#[Group('paatokset_ahjo_api')]
class SpecialStatusTest extends AhjoSearchApiKernelTestBase {

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
  public function testProcessor(): void {
    $decision = Decision::create([
      'type' => 'decision',
      'title' => 'Test decision',
      'field_policymaker_id' => PolicymakerService::CITY_COUNCIL_DM_ID,
    ]);

    $id = Utility::createCombinedId('entity:node', $decision->id() . ':' . $decision->language()->getId());

    $fields = $this->container
      ->get('search_api.fields_helper')
      ->createItemFromObject($this->index, $decision->getTypedData(), $id)
      ->getFields();

    $this->assertEquals([SpecialStatus::CITY_COUNCIL], $fields['test_special_status']->getValues());

    $decision->set('field_policymaker_id', PolicymakerService::CITY_BOARD_DM_ID);
    $fields = $this->container
      ->get('search_api.fields_helper')
      ->createItemFromObject($this->index, $decision->getTypedData(), $id)
      ->getFields();

    $this->assertEquals([SpecialStatus::CITY_HALL], $fields['test_special_status']->getValues());
  }

}
