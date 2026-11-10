<?php

declare(strict_types=1);

namespace Drupal\Tests\paatokset_ahjo_api\Kernel\Plugin\Condition;

use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\paatokset_ahjo_api\Plugin\Condition\CaseOrDecision;
use Drupal\Tests\paatokset_ahjo_api\Kernel\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the case or decision condition.
 */
#[Group('paatokset_ahjo_api')]
#[RunTestsInSeparateProcesses]
class CaseOrDecisionTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'node',
  ];

  /**
   * {@inheritDoc}
   */
  protected function setUp(): void {
    parent::setUp();

    foreach (['case', 'decision', 'page'] as $type) {
      NodeType::create(['type' => $type, 'name' => $type])->save();
    }
  }

  /**
   * Tests condition evaluation.
   */
  public function testEvaluate(): void {
    /** @var \Drupal\Core\Condition\ConditionManager $manager */
    $manager = $this->container->get('plugin.manager.condition');

    $condition = $manager->createInstance('paatokset_case_or_decision');
    $negated = $manager->createInstance('paatokset_case_or_decision', ['negate' => TRUE]);
    $this->assertInstanceOf(CaseOrDecision::class, $condition);
    $this->assertInstanceOf(CaseOrDecision::class, $negated);

    // No node, e.g. a non-node route. The negated condition must pass.
    $this->assertFalse($manager->execute($condition));
    $this->assertTrue($manager->execute($negated));

    foreach (['case' => TRUE, 'decision' => TRUE, 'page' => FALSE] as $bundle => $expected) {
      $node = Node::create(['type' => $bundle, 'title' => $bundle]);
      $condition->setContextValue('node', $node);
      $negated->setContextValue('node', $node);

      $this->assertSame($expected, $manager->execute($condition), $bundle);
      $this->assertSame(!$expected, $manager->execute($negated), $bundle);
    }
  }

}
