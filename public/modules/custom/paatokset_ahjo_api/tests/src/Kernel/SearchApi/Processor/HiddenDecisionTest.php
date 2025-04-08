<?php

declare(strict_types=1);

namespace Drupal\Tests\paatokset_ahjo_api\Kernel\SearchApi\Processor;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\search_api\Item\Field;
use Drupal\search_api\Utility\Utility;
use Drupal\Tests\paatokset_ahjo_api\Kernel\SearchApi\AhjoSearchApiKernelTestBase;

/**
 * Tests hidden_decisions search_api processor.
 */
class HiddenDecisionTest extends AhjoSearchApiKernelTestBase {

  /**
   * {@inheritdoc}
   */
  public function setUp($processor = NULL): void {
    parent::setUp('hidden_decisions');

    // Hiding is hard-coded to act on fields
    // decision_content and decision_motion.
    $field = new Field($this->index, 'decision_content');
    $field->setType('string');
    $field->setPropertyPath('title');
    $field->setDatasourceId('entity:node');
    $field->setLabel('Content');

    $this->index->addField($field);

    $field = new Field($this->index, 'decision_motion');
    $field->setType('string');
    $field->setPropertyPath('title');
    $field->setDatasourceId('entity:node');
    $field->setLabel('Motion');

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
    ]);
    $this->assertInstanceOf(ContentEntityInterface::class, $decision);

    $id = Utility::createCombinedId('entity:node', $decision->id() . ':' . $decision->language()->getId());
    $item = $this->container
      ->get('search_api.fields_helper')
      ->createItemFromObject($this->index, $decision->getTypedData(), $id);

    // Extract fields.
    $item->getFields();

    // Preprocess items.
    $this->index->preprocessIndexItems([$item]);

    $fields = $item->getFields();

    // Fields are visible.
    $this->assertEquals(['Test decision'], $fields['decision_content']->getValues());
    $this->assertEquals(['Test decision'], $fields['decision_motion']->getValues());

    $decision->set('field_hide_decision_content', '1');
    $item = $this->container
      ->get('search_api.fields_helper')
      ->createItemFromObject($this->index, $decision->getTypedData(), $id);

    // Extract fields.
    $item->getFields();

    // Preprocess items.
    $this->index->preprocessIndexItems([$item]);

    $fields = $item->getFields();

    // Fields are hidden.
    $this->assertArrayNotHasKey('decision_content', $fields);
    $this->assertArrayNotHasKey('decision_motion', $fields);
  }

}
