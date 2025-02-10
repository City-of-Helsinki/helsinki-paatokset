<?php

declare(strict_types=1);

namespace Drupal\Tests\paatokset_allu\Kernel;

use Drupal\helfi_api_base\Entity\RemoteEntityInterface;
use Drupal\paatokset_allu\Client\Client;
use Drupal\paatokset_allu\Entity\Document;
use Drupal\Tests\helfi_api_base\Kernel\Entity\Access\RemoteEntityAccessTestBase;
use Drupal\Tests\helfi_api_base\Traits\ApiTestTrait;
use Prophecy\PhpUnit\ProphecyTrait;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Kernel tests for document entity.
 */
class DocumentEntityTest extends RemoteEntityAccessTestBase {

  use ProphecyTrait;
  use ApiTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'user',
    'helfi_api_base',
    'paatokset_allu',
  ];

  /**
   * {@inheritDoc}
   */
  protected function setUpRemoteEntity(): RemoteEntityInterface {
    $this->installEntitySchema('paatokset_allu_document');

    return Document::create([
      'label' => 'test',
    ]);
  }

  /**
   * Tests canonical route.
   */
  public function testCanonicalRoute(): void {
    $client = $this->prophesize(Client::class);

    // Canonical route should return response from the API.
    $client->streamDecision($this->rmt->id())
      ->shouldBeCalled()
      ->willReturn(new StreamedResponse(static function () {}));

    $this->container->set(Client::class, $client->reveal());

    $url = $this->rmt->toUrl()->toString();
    $response = $this->processRequest($this->getMockedRequest($url));

    $this->assertEquals(Response::HTTP_FORBIDDEN, $response->getStatusCode());

    $this->drupalSetUpCurrentUser(permissions: ['view remote entities']);
    $this->processRequest($this->getMockedRequest($url));
  }

}
