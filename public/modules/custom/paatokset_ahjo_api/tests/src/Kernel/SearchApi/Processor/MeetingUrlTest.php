<?php

declare(strict_types=1);

namespace Drupal\Tests\paatokset_ahjo_api\Kernel\SearchApi\Processor;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Url;
use Drupal\paatokset_policymakers\Service\PolicymakerService;
use Drupal\search_api\Item\Field;
use Drupal\search_api\Utility\Utility;
use Drupal\Tests\paatokset_ahjo_api\Kernel\SearchApi\AhjoSearchApiKernelTestBase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;

/**
 * Tests meeting_url search_api processor.
 */
class MeetingUrlTest extends AhjoSearchApiKernelTestBase {

  use ProphecyTrait;

  /**
   * Policymaker service mock.
   */
  private PolicymakerService|ObjectProphecy $policyMakerService;

  /**
   * {@inheritdoc}
   */
  public function setUp($processor = NULL): void {
    parent::setUp('meeting_url');

    $field = new Field($this->index, 'test_meeting_url');
    $field->setType('string');
    $field->setPropertyPath('meeting_url');
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

    $this->policyMakerService = $this->prophesize(PolicyMakerService::class);
    $this->container->set('paatokset_policymakers', $this->policyMakerService->reveal());
  }

  /**
   * Tests color class processor.
   */
  public function testProcessor() {
    $url = $this->prophesize(Url::class);
    $url->toString()->willReturn('test-url');

    $this->policyMakerService
      ->getMinutesRoute(Argument::any(), Argument::any(), Argument::any(), Argument::any())
      ->willReturn($url->reveal());

    /** @var \Drupal\Core\Entity\EntityStorageInterface $storage */
    $storage = $this->container->get(EntityTypeManagerInterface::class)
      ->getStorage('node');

    $meeting = $storage->create([
      'type' => 'meeting',
      'title' => 'Test meeting',
      'field_meeting_id' => '123',
      'field_meeting_dm_id' => '234',
    ]);

    $this->assertInstanceOf(ContentEntityInterface::class, $meeting);

    $id = Utility::createCombinedId('entity:node', $meeting->id() . ':' . $meeting->language()->getId());
    $item = $this->container
      ->get('search_api.fields_helper')
      ->createItemFromObject($this->index, $meeting->getTypedData(), $id);
    $fields = $item->getFields();

    $this->assertEquals([
      'meeting_link' => [
        'fi' => 'test-url',
        'sv' => 'test-url',
        'en' => 'test-url',
      ],
      'decision_link' => [],
    ], json_decode($fields['test_meeting_url']->getValues()[0], TRUE));
  }

}
