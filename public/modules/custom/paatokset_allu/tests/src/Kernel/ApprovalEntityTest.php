<?php

declare(strict_types=1);

namespace Drupal\Tests\paatokset_allu\Kernel;

use Drupal\Core\Url;
use Drupal\helfi_api_base\Entity\RemoteEntityInterface;
use Drupal\paatokset_allu\ApprovalType;
use Drupal\paatokset_allu\Client\Client;
use Drupal\paatokset_allu\Entity\Approval;
use Drupal\paatokset_allu\Entity\Document;
use Drupal\Tests\helfi_api_base\Kernel\Entity\Access\RemoteEntityAccessTestBase;
use Drupal\Tests\helfi_api_base\Traits\ApiTestTrait;
use GuzzleHttp\Psr7\Response as GuzzleResponse;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Prophecy\PhpUnit\ProphecyTrait;
use Symfony\Component\HttpFoundation\Response;

/**
 * Kernel tests for document entity.
 */
#[Group('paatokset_allu')]
#[RunTestsInSeparateProcesses]
class ApprovalEntityTest extends RemoteEntityAccessTestBase {

  use ProphecyTrait;
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
    $this->installEntitySchema('paatokset_allu_approval');

    $document = Document::create([
      'label' => 'test',
    ]);
    $document->save();

    return Approval::create([
      'label' => 'test',
      'type' => ApprovalType::WORK_FINISHED->value,
      'document' => $document,
    ]);
  }

  /**
   * Tests canonical route.
   */
  public function testCanonicalRoute(): void {
    $client = $this->prophesize(Client::class);

    // Canonical route should return response from the API.
    $client
      ->approvalDocument($this->rmt->get('document')->target_id, ApprovalType::from($this->rmt->get('type')->value))
      ->shouldBeCalled()
      ->willReturn(new GuzzleResponse(body: 'pdf'));

    $this->container->set(Client::class, $client->reveal());

    $canonical = $this->rmt->toUrl()->toString();
    $response = $this->processRequest($this->getMockedRequest($canonical));

    $this->assertEquals(Response::HTTP_FORBIDDEN, $response->getStatusCode());

    $document = $this->rmt->get('document')->entity;
    $direct = Url::fromRoute('entity.paatokset_allu_document.approval', [
      'paatokset_allu_document' => $document->id(),
      'type' => $this->rmt->get('type')->value,
    ])->toString();
    $response = $this->processRequest($this->getMockedRequest($direct));

    $this->assertEquals(Response::HTTP_FORBIDDEN, $response->getStatusCode());

    $this->drupalSetUpCurrentUser(permissions: ['view remote entities']);
    foreach ([$canonical, $direct] as $url) {
      $response = $this->processRequest($this->getMockedRequest($url));
      $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
      $this->assertStringContainsString('filename=test-work_finished.pdf', $response->headers->get('Content-Disposition'));
    }
  }

}
