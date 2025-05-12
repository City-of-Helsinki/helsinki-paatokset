<?php

declare(strict_types=1);

namespace Drupal\Tests\paatokset_ahjo_api\Kernel\Controller;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Url;
use Drupal\Tests\helfi_api_base\Traits\ApiTestTrait;
use Drupal\Tests\paatokset_ahjo_api\Kernel\AhjoKernelTestBase;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Symfony\Component\HttpFoundation\Response;

/**
 * Tests case controller.
 */
class AhjoApiControllerTest extends AhjoKernelTestBase {

  use ApiTestTrait;
  use UserCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'helfi_api_base',
    'paatokset_ahjo_api',
  ];

  /**
   * {@inheritDoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('ahjo_organization');
  }

  /**
   * Tests case controller.
   */
  public function testOrgChartController(): void {
    /** @var \Drupal\Core\Entity\EntityStorageInterface $storage */
    $storage = $this->container->get(EntityTypeManagerInterface::class)
      ->getStorage('ahjo_organization');

    $storage->create([
      'id' => '00001',
      'title' => 'Helsingin kaupunki',
    ])->save();
    $storage->create([
      'id' => '00002',
      'title' => 'Unpublished test',
      'organization_above' => '00001',
      'existing' => 0,
    ])->save();
    $storage->create([
      'id' => '02900',
      'title' => 'Kaupunginvaltuusto',
      'organization_above' => '00001',
    ])->save();
    $storage->create([
      'id' => '00400',
      'title' => 'Kaupunginhallitus',
      'organization_above' => '02900',
    ])->save();

    // Test invalid permissions.
    $this->assertEquals(403, $this->getOrgChartResponse('00001', 1)->getStatusCode());

    // Setup permissions.
    $this->setUpCurrentUser(permissions: ['view remote entities']);

    // Test correct permissions.
    $response = $this->getOrgChartResponse('00001', 1);
    $this->assertEquals(200, $response->getStatusCode());
    $this->assertEquals([
      'id' => '00001',
      'title' => 'Helsingin kaupunki',
    ], json_decode($response->getContent(), TRUE));

    // Not found.
    $this->assertEquals(404, $this->getOrgChartResponse('not-found', 1)->getStatusCode());

    // Too many steps.
    $this->assertEquals(400, $this->getOrgChartResponse('00001', 9999)->getStatusCode());

    // Nested structure.
    $response = $this->getOrgChartResponse('00001', 3);
    $this->assertEquals(200, $response->getStatusCode());
    $this->assertEquals([
      'id' => '00001',
      'title' => 'Helsingin kaupunki',
      'children' => [
        [
          'id' => '02900',
          'title' => 'Kaupunginvaltuusto',
          'children' => [
            [
              'id' => '00400',
              'title' => 'Kaupunginhallitus',
            ],
          ],
        ],
      ],
    ], json_decode($response->getContent(), TRUE));

    /** @var \Drupal\Core\Entity\EntityStorageInterface $storage */
    $nodeStorage = $this->container->get(EntityTypeManagerInterface::class)
      ->getStorage('node');

    // Create policymaker node.
    $policymaker = $nodeStorage->create([
      'type' => 'policymaker',
      'status' => '1',
      'langcode' => 'en',
      'title' => 'Test policymaker',
      'field_policymaker_id' => '02900',
    ]);
    $policymaker->save();

    // Test with policymaker.
    $response = $this->getOrgChartResponse('00001', 3);
    $this->assertEquals(200, $response->getStatusCode());
    $this->assertEquals([
      'id' => '00001',
      'title' => 'Helsingin kaupunki',
      'children' => [
        [
          'id' => '02900',
          'title' => 'Kaupunginvaltuusto',
          'url' => $policymaker->toUrl('canonical', ['absolute' => TRUE])->toString(),
          'children' => [
            [
              'id' => '00400',
              'title' => 'Kaupunginhallitus',
            ],
          ],
        ],
      ],
    ], json_decode($response->getContent(), TRUE));
  }

  /**
   * Processes mocked response to org chart endpoint.
   */
  private function getOrgChartResponse(string $id, int $steps): Response {
    $url = Url::fromRoute('paatokset_ahjo_api.org_chart', [
      'ahjo_organization' => $id,
      'steps' => $steps,
    ]);

    $request = $this->getMockedRequest($url->toString());
    return $this->processRequest($request);
  }

}
