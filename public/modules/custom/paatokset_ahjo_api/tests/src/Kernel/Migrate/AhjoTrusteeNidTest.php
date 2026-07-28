<?php

declare(strict_types=1);

namespace Drupal\Tests\paatokset_ahjo_api\Kernel\Migrate;

use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\Plugin\MigrateIdMapInterface;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\migrate\Row;
use Drupal\node\Entity\Node;
use Drupal\paatokset_ahjo_api\Plugin\migrate\process\AhjoTrusteeNid;
use Drupal\Tests\paatokset_ahjo_api\Kernel\AhjoEntityKernelTestBase;
use Drupal\Tests\user\Traits\UserCreationTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Prophecy\Argument;
use Prophecy\Prophecy\ObjectProphecy;
use Psr\Log\LoggerInterface;

/**
 * Tests the `ahjo_trustee_nid` process plugin.
 */
#[Group('paatokset_ahjo_api')]
#[RunTestsInSeparateProcesses]
class AhjoTrusteeNidTest extends AhjoEntityKernelTestBase {

  use UserCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // The plugin looks up trustees with accessCheck(TRUE).
    $this->setCurrentUser($this->createUser(['access content']));
  }

  /**
   * Empty source values return NULL and do not touch the id map.
   */
  public function testEmptyValue(): void {
    $idMap = $this->prophesize(MigrateIdMapInterface::class);
    $idMap->lookupDestinationIds(Argument::any())->shouldNotBeCalled();
    $idMap->delete(Argument::any())->shouldNotBeCalled();

    $this->assertNull($this->doTransform(NULL, $idMap));
    $this->assertNull($this->doTransform('', $idMap));
  }

  /**
   * Returns NULL when no trustee exists for the agent id.
   */
  public function testLookupMiss(): void {
    $idMap = $this->prophesize(MigrateIdMapInterface::class);
    $idMap->lookupDestinationIds(['agent_id' => 'missing-agent'])
      ->willReturn([])
      ->shouldBeCalledOnce();
    $idMap->delete(Argument::any())->shouldNotBeCalled();

    $this->assertNull($this->doTransform('missing-agent', $idMap));
  }

  /**
   * Returns the existing trustee nid when there is no map entry.
   */
  public function testLookupHitWithoutMapEntry(): void {
    $nid = $this->createTrustee('agent-1');

    $idMap = $this->prophesize(MigrateIdMapInterface::class);
    $idMap->lookupDestinationIds(['agent_id' => 'agent-1'])
      ->willReturn([])
      ->shouldBeCalledOnce();
    $idMap->delete(Argument::any())->shouldNotBeCalled();

    $this->assertSame($nid, $this->doTransform('agent-1', $idMap));
  }

  /**
   * Leaves the map alone when mapped destid matches the looked-up nid.
   */
  public function testMatchingMapEntryIsKept(): void {
    $nid = $this->createTrustee('agent-2');

    $idMap = $this->prophesize(MigrateIdMapInterface::class);
    $idMap->lookupDestinationIds(['agent_id' => 'agent-2'])
      ->willReturn([[$nid]])
      ->shouldBeCalledOnce();
    $idMap->delete(Argument::any())->shouldNotBeCalled();

    $logger = $this->prophesize(LoggerInterface::class);
    $logger->notice(Argument::cetera())->shouldNotBeCalled();

    $this->assertSame($nid, $this->doTransform('agent-2', $idMap, $logger));
  }

  /**
   * Deletes a stale map row when destid does not match the looked-up nid.
   */
  public function testStaleMapEntryIsDeleted(): void {
    $nid = $this->createTrustee('agent-3');

    $idMap = $this->prophesize(MigrateIdMapInterface::class);
    $idMap->lookupDestinationIds(['agent_id' => 'agent-3'])
      ->willReturn([['99999']])
      ->shouldBeCalledOnce();
    $idMap->delete(['agent_id' => 'agent-3'])->shouldBeCalledOnce();

    $logger = $this->prophesize(LoggerInterface::class);
    $logger->notice(Argument::containingString('Removing stale migrate map entry'), Argument::any())
      ->shouldBeCalledOnce();

    $this->assertSame($nid, $this->doTransform('agent-3', $idMap, $logger));
  }

  /**
   * Deletes a map row when a mapped node no longer exists for the agent id.
   */
  public function testStaleMapEntryDeletedWhenTrusteeMissing(): void {
    $idMap = $this->prophesize(MigrateIdMapInterface::class);
    $idMap->lookupDestinationIds(['agent_id' => 'agent-4'])
      ->willReturn([['99999']])
      ->shouldBeCalledOnce();
    $idMap->delete(['agent_id' => 'agent-4'])->shouldBeCalledOnce();

    $logger = $this->prophesize(LoggerInterface::class);
    $logger->notice(Argument::cetera())->shouldBeCalledOnce();

    $this->assertNull($this->doTransform('agent-4', $idMap, $logger));
  }

  /**
   * Creates a published trustee node with the given agent id.
   */
  private function createTrustee(string $agent_id): string {
    $node = Node::create([
      'type' => 'trustee',
      'title' => 'Test Trustee',
      'status' => 1,
      'field_trustee_id' => $agent_id,
    ]);
    $node->save();

    return (string) $node->id();
  }

  /**
   * Runs the process plugin transform.
   *
   * @param string|null $value
   *   Source agent id.
   * @param \Prophecy\Prophecy\ObjectProphecy<\Drupal\migrate\Plugin\MigrateIdMapInterface> $idMap
   *   Id map prophecy.
   * @param \Prophecy\Prophecy\ObjectProphecy<\Psr\Log\LoggerInterface>|null $logger
   *   Optional logger prophecy.
   *
   * @return string|null
   *   Transformed nid.
   */
  private function doTransform(?string $value, ObjectProphecy $idMap, ?ObjectProphecy $logger = NULL): ?string {
    $migration = $this->prophesize(MigrationInterface::class);
    $migration->getIdMap()->willReturn($idMap->reveal());

    $logger ??= $this->prophesize(LoggerInterface::class);

    $plugin = new AhjoTrusteeNid(
      [],
      'ahjo_trustee_nid',
      [],
      $migration->reveal(),
      $this->container->get('entity_type.manager'),
      $logger->reveal(),
    );

    $executable = $this->prophesize(MigrateExecutableInterface::class)->reveal();
    $row = new Row(['agent_id' => $value ?? ''], ['agent_id' => []]);

    return $plugin->transform($value, $executable, $row, 'nid');
  }

}
