<?php

declare(strict_types=1);

namespace Drupal\Tests\paatokset\Kernel;

use Drupal\Core\Datetime\Entity\DateFormat;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\NodeType;
use Drupal\node\NodeInterface;
use Drupal\Tests\helfi_api_base\Traits\ApiTestTrait;
use Drupal\Tests\node\Traits\NodeCreationTrait;
use Drupal\Tests\user\Traits\UserCreationTrait;

/**
 * Tests ExceptionSubscriber.
 *
 * @coversDefaultClass \Drupal\paatokset\EventSubscriber\ExceptionSubscriber
 */
class ExceptionSubscriberTest extends KernelTestBase {

  use NodeCreationTrait;
  use UserCreationTrait;
  use ApiTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'serialization',
    'paatokset',
    'system',
    'node',
    'user',
  ];

  /**
   * {@inheritDoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('date_format');
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');

    DateFormat::create([
      'id' => 'fallback',
      'label' => 'Fallback',
      'pattern' => 'Y-m-d',
    ])->save();

    NodeType::create([
      'name' => $this->randomMachineName(),
      'type' => 'article',
    ])->save();
  }

  /**
   * Tests exception subscriber.
   */
  public function testExceptionSubscriber(): void {
    $admin = $this->setUpCurrentUser(permissions: [
      'view own unpublished content',
      'access content',
    ]);

    $valid = $this->createNode([
      'status' => NodeInterface::PUBLISHED,
      'uid' => $admin->id(),
      'type' => 'article',
    ]);

    // Users with correct permissions can access content.
    $request = $this->getMockedRequest($valid->toUrl()->toString());
    $response = $this->processRequest($request);
    $this->assertEquals(200, $response->getStatusCode());

    $unpublished = $this->createNode([
      'status' => NodeInterface::NOT_PUBLISHED,
      'uid' => $admin->id(),
      'type' => 'article',
    ]);

    // Users with correct permissions can access unpublished content.
    $request = $this->getMockedRequest($unpublished->toUrl()->toString());
    $response = $this->processRequest($request);
    $this->assertEquals(200, $response->getStatusCode());

    $this->setUpCurrentUser(permissions: [
      'access content',
    ]);

    // Do nothing if redirect is not configured.
    $request = $this->getMockedRequest($unpublished->toUrl()->toString());
    $response = $this->processRequest($request);
    $this->assertEquals(403, $response->getStatusCode());

    // Add redirect destination.
    $this->config('paatokset.settings')
      ->set('redirect_403_page', $valid->id())
      ->save();

    // Redirect with 410 status code.
    $request = $this->getMockedRequest($unpublished->toUrl()->toString());
    $response = $this->processRequest($request);
    $this->assertEquals(410, $response->getStatusCode());
  }

}
