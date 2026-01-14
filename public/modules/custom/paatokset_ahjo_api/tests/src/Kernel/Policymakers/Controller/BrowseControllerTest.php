<?php

declare(strict_types=1);

namespace Drupal\Tests\paatokset_ahjo_api\Kernel\Policymakers\Controller;

use Drupal\Core\Url;
use Drupal\paatokset_ahjo_api\Entity\Organization;
use Drupal\paatokset_ahjo_api\Entity\Policymaker;
use Drupal\Tests\helfi_api_base\Traits\ApiTestTrait;
use Drupal\Tests\paatokset_ahjo_api\Kernel\AhjoKernelTestBase;
use Drupal\Tests\user\Traits\UserCreationTrait;

/**
 * Tests policymaker controller.
 */
class BrowseControllerTest extends AhjoKernelTestBase {

  use ApiTestTrait;
  use UserCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'paatokset_ahjo_api',
    'user',
  ];

  /**
   * {@inheritDoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('ahjo_organization');
  }

  /**
   * Tests the document route.
   */
  public function testRoute(): void {
    $this->setUpCurrentUser(permissions: ['access content']);

    $organization = Organization::create([
      'id' => '00400',
      'label' => 'Kaupunginhallitus',
      'existing' => 1,
    ]);
    $organization->save();

    $policymaker = Policymaker::create([
      'title' => 'Test policymaker',
      'field_policymaker_id' => '00400',
    ]);
    $policymaker->save();

    $request = $this->getMockedRequest(Url::fromRoute('paatokset_ahjo_api.browse_policymakers', [
      'org' => 'does-not-exist',
    ])->toString());
    $response = $this->processRequest($request);
    $this->assertEquals(404, $response->getStatusCode());

    $request = $this->getMockedRequest(Url::fromRoute('paatokset_ahjo_api.browse_policymakers', [
      'org' => '00400',
    ])->toString());
    $response = $this->processRequest($request);
    $this->assertEquals(200, $response->getStatusCode());

  }

}
