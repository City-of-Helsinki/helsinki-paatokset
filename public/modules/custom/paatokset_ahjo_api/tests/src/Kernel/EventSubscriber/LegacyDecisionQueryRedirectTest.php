<?php

declare(strict_types=1);

namespace Drupal\Tests\paatokset_ahjo_api\Kernel\EventSubscriber;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\node\NodeInterface;
use Drupal\paatokset_ahjo_api\EventSubscriber\LegacyDecisionQueryRedirect;
use Drupal\Tests\paatokset_ahjo_api\Kernel\AhjoEntityKernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * Tests legacy decision query parameter redirects.
 *
 * @see \Drupal\paatokset_ahjo_api\EventSubscriber\LegacyDecisionQueryRedirect
 */
#[Group('paatokset_ahjo_api')]
#[RunTestsInSeparateProcesses]
class LegacyDecisionQueryRedirectTest extends AhjoEntityKernelTestBase {

  /**
   * Creates a node.
   *
   * @param array<string, mixed> $values
   *   Node values.
   */
  private function createNode(array $values): NodeInterface {
    $node = $this->container->get(EntityTypeManagerInterface::class)
      ->getStorage('node')
      ->create($values + [
        'title' => 'Test',
        'status' => 1,
      ]);
    $node->save();

    assert($node instanceof NodeInterface);
    return $node;
  }

  /**
   * Dispatches request event and returns the response, if set.
   *
   * @param \Drupal\node\NodeInterface $node
   *   Route node.
   * @param array<string, string> $query
   *   Query parameters.
   * @param string $route
   *   Route name.
   */
  private function getResponse(NodeInterface $node, array $query, string $route = 'entity.node.canonical'): ?RedirectResponse {
    $request = Request::create('/node/' . $node->id(), parameters: $query);
    $request->attributes->set('_route', $route);
    $request->attributes->set('node', $node);

    $event = new RequestEvent($this->container->get('http_kernel'), $request, HttpKernelInterface::MAIN_REQUEST);
    $this->container->get(LegacyDecisionQueryRedirect::class)->onRequest($event);

    $response = $event->getResponse();
    $this->assertTrue($response === NULL || $response instanceof RedirectResponse);

    return $response;
  }

  /**
   * Tests the redirect.
   */
  public function testRedirect(): void {
    $case = $this->createNode([
      'type' => 'case',
      'field_diary_number' => 'HEL-2025-000001',
    ]);

    $decision = $this->createNode([
      'type' => 'decision',
      'field_diary_number' => 'HEL-2025-000001',
      'field_decision_native_id' => '{TEST-NATIVE-ID}',
    ]);

    $this->createNode([
      'type' => 'decision',
      'field_diary_number' => 'HEL-2025-000002',
      'field_decision_native_id' => '{OTHER-NATIVE-ID}',
    ]);

    foreach (['paatos', 'beslut', 'decision'] as $key) {
      $response = $this->getResponse($case, [$key => 'test-native-id']);
      $this->assertNotNull($response, "Query key $key redirects");
      $this->assertEquals(301, $response->getStatusCode());
      $this->assertEquals($decision->toUrl()->toString(), $response->getTargetUrl());
    }

    // The decision must belong to the case.
    $this->assertNull($this->getResponse($case, ['paatos' => 'other-native-id']));

    // Unknown decisions display the case.
    $this->assertNull($this->getResponse($case, ['paatos' => 'does-not-exist']));

    // Requests without the query parameter are not redirected.
    $this->assertNull($this->getResponse($case, []));

    // Only case pages are redirected.
    $this->assertNull($this->getResponse($decision, ['paatos' => 'test-native-id']));
    $this->assertNull($this->getResponse($case, ['paatos' => 'test-native-id'], 'entity.node.edit_form'));
  }

}
