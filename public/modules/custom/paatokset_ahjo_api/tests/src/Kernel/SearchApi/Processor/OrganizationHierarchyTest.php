<?php

declare(strict_types=1);

namespace Drupal\Tests\paatokset_ahjo_api\Kernel\SearchApi\Processor;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\paatokset_ahjo_api\Service\OrganizationPathBuilder;
use Drupal\search_api\Item\Field;
use Drupal\search_api\Utility\Utility;
use Drupal\Tests\paatokset_ahjo_api\Kernel\SearchApi\AhjoSearchApiKernelTestBase;
use Prophecy\Argument;
use Prophecy\Prophecy\ObjectProphecy;
use Symfony\Component\DependencyInjection\Loader\Configurator\Traits\PropertyTrait;

/**
 * Tests organization_hierarchy search_api processor.
 *
 * @see \Drupal\paatokset_ahjo_api\Plugin\search_api\processor\OrganizationHierarchy
 */
class OrganizationHierarchyTest extends AhjoSearchApiKernelTestBase {

  use PropertyTrait;

  /**
   * Organization path builder.
   */
  private OrganizationPathBuilder|ObjectProphecy $organizationPathBuilder;

  /**
   * {@inheritdoc}
   */
  public function setUp($processor = NULL): void {
    parent::setUp();

    $this->installEntitySchema('ahjo_organization');

    $this->processor = $this->container
      ->get('search_api.plugin_helper')
      ->createProcessorPlugin($this->index, 'organization_hierarchy');
    $this->index->addProcessor($this->processor);

    $field = new Field($this->index, 'test_organization_hierarchy');
    $field->setType('string');
    $field->setPropertyPath('organization_hierarchy');
    $field->setDatasourceId('entity:node');
    $field->setLabel('Test field');

    $this->index->addField($field);
    $this->index->save();
  }

  /**
   * {@inheritDoc}
   */
  protected function bootKernel(): void {
    parent::bootKernel();

    $this->organizationPathBuilder = $this->prophesize(OrganizationPathBuilder::class);
    $this->container->set(OrganizationPathBuilder::class, $this->organizationPathBuilder->reveal());
  }

  /**
   * Tests color class processor.
   */
  public function testProcessor() {
    $this->organizationPathBuilder
      ->build(Argument::any())
      ->willReturn([
        '#organizations' => [
          [
            'title' => 'foo',
          ],
          [
            'title' => 'bar',
          ],
        ],
      ]);

    /** @var \Drupal\Core\Entity\EntityStorageInterface $storage */
    $storage = $this->container->get(EntityTypeManagerInterface::class)
      ->getStorage('node');

    $policymaker = $storage->create([
      'type' => 'policymaker',
      'status' => '1',
      'langcode' => 'en',
      'title' => 'Test policymaker',
      'field_policymaker_id' => '123',
      'field_organization_color_code' => 'test-color',
    ]);
    $policymaker->save();

    $id = Utility::createCombinedId('entity:node', $policymaker->id() . ':' . $policymaker->language()->getId());
    $item = $this->container
      ->get('search_api.fields_helper')
      ->createItemFromObject($this->index, $policymaker->getTypedData(), $id);

    // Extract field values and check the value.
    $fields = $item->getFields();

    $this->assertEquals(['foo', 'bar'], $fields['test_organization_hierarchy']->getValues());
  }

}
