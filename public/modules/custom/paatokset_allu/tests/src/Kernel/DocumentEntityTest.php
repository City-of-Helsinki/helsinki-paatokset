<?php

declare(strict_types=1);

namespace Drupal\Tests\paatokset_allu\Kernel;

use Drupal\Core\State\StateInterface;
use Drupal\helfi_api_base\Entity\RemoteEntityInterface;
use Drupal\paatokset_allu\Client\Settings;
use Drupal\paatokset_allu\Client\TokenFactory;
use Drupal\paatokset_allu\Entity\Document;
use Drupal\Tests\helfi_api_base\Kernel\Entity\Access\RemoteEntityAccessTestBase;
use Drupal\Tests\helfi_api_base\Traits\ApiTestTrait;
use GuzzleHttp\Psr7\Response as GuzzleResponse;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\Response;

/**
 * Kernel tests for document entity.
 */
#[Group('paatokset_allu')]
#[RunTestsInSeparateProcesses]
class DocumentEntityTest extends RemoteEntityAccessTestBase {

  use ApiTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'user',
    'helfi_api_base',
    'paatokset_allu',
    'diff',
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
    $payload = base64_encode(json_encode(['exp' => time() + 3600], JSON_THROW_ON_ERROR));

    // Store a valid token so TokenFactory does not need to log in.
    $this->container->get(StateInterface::class)
      ->set(TokenFactory::TOKEN_STATE, "header.$payload.signature");
    $this->container->set(Settings::class, new Settings('user', 'pass', 'https://allu.test'));

    $this->container->set('http_client', $this->createMockHttpClient([
      new GuzzleResponse(headers: ['Content-Length' => '3'], body: 'pdf'),
      new GuzzleResponse(Response::HTTP_NOT_FOUND),
    ]));

    $url = $this->rmt->toUrl()->toString();
    $response = $this->processRequest($this->getMockedRequest($url));

    $this->assertEquals(Response::HTTP_FORBIDDEN, $response->getStatusCode());

    $this->drupalSetUpCurrentUser(permissions: ['view remote entities']);
    $response = $this->processRequest($this->getMockedRequest($url));

    $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
    $this->assertEquals('application/pdf', $response->headers->get('Content-Type'));
    $this->assertEquals('3', $response->headers->get('Content-Length'));
    $this->assertStringContainsString('filename=test.pdf', $response->headers->get('Content-Disposition'));

    ob_start();
    $response->sendContent();
    $this->assertEquals('pdf', ob_get_clean());

    // API errors are not returned as PDF documents.
    $response = $this->processRequest($this->getMockedRequest($url));
    $this->assertEquals(Response::HTTP_NOT_FOUND, $response->getStatusCode());
  }

}
