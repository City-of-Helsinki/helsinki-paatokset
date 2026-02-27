<?php

declare(strict_types=1);

namespace Drupal\Tests\paatokset_ahjo_api\Kernel\SearchApi\Processor;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\node\NodeInterface;
use Drupal\search_api\Item\Field;
use Drupal\search_api\Item\ItemInterface;
use Drupal\search_api\Utility\Utility;
use Drupal\Tests\paatokset_ahjo_api\Kernel\SearchApi\AhjoSearchApiKernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests organization_type_fallback search_api processor.
 */
#[Group('paatokset_ahjo_api')]
#[RunTestsInSeparateProcesses]
class OrganizationTypeTest extends AhjoSearchApiKernelTestBase {

  /**
   * {@inheritdoc}
   */
  public function setUp($processor = NULL): void {
    parent::setUp('organization_type_fallback');

    $field = new Field($this->index, 'organization_type');
    $field->setType('string');
    $field->setPropertyPath('title');
    $field->setDatasourceId('entity:node');
    $field->setLabel('Organization type');

    $this->index->addField($field);
    $this->index->save();
  }

  /**
   * Tests search api processor.
   */
  public function testProcessor(): void {
    $storage = $this->container->get(EntityTypeManagerInterface::class)
      ->getStorage('node');

    $policymaker = $storage->create([
      'type' => 'policymaker',
      'status' => 1,
      'langcode' => 'en',
      'title' => 'Test policymaker',
      'field_policymaker_id' => '123',
      'field_organization_type' => '11',
    ]);
    $policymaker->save();

    $decision = $storage->create([
      'type' => 'decision',
      'title' => 'Test decision',
      'field_policymaker_id' => '123',
    ]);

    // Empty organization_type should get fallback from policymaker.
    $item = $this->createItem($decision);
    $item->getFields()['organization_type']->setValues([]);
    $this->index->preprocessIndexItems([$item]);
    $this->assertEquals(['11'], $item->getFields()['organization_type']->getValues());

    // Non-empty organization_type should be kept as-is.
    $item = $this->createItem($decision);
    $item->getFields()['organization_type']->setValues(['existing']);
    $this->index->preprocessIndexItems([$item]);
    $this->assertEquals(['existing'], $item->getFields()['organization_type']->getValues());

    // No matching policymaker: empty value stays empty.
    $decision = $storage->create([
      'type' => 'decision',
      'title' => 'Test decision 2',
      'field_policymaker_id' => 'missing',
    ]);
    $item = $this->createItem($decision);
    $item->getFields()['organization_type']->setValues([]);
    $this->index->preprocessIndexItems([$item]);
    $this->assertEquals([], $item->getFields()['organization_type']->getValues());
  }

  /**
   * Creates a search api item from a node.
   */
  private function createItem(NodeInterface $node): ItemInterface {
    $id = Utility::createCombinedId('entity:node', $node->id() . ':' . $node->language()->getId());
    return $this->container
      ->get('search_api.fields_helper')
      ->createItemFromObject($this->index, $node->getTypedData(), $id);
  }

}
