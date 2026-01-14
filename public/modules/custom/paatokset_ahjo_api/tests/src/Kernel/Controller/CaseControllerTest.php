<?php

declare(strict_types=1);

namespace Drupal\Tests\paatokset_ahjo_api\Kernel\Controller;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Url;
use Drupal\Tests\helfi_api_base\Traits\ApiTestTrait;
use Drupal\Tests\paatokset_ahjo_api\Kernel\AhjoEntityKernelTestBase;

/**
 * Tests case controller.
 */
class CaseControllerTest extends AhjoEntityKernelTestBase {

  use ApiTestTrait;

  /**
   * Tests case controller.
   */
  public function testCaseController(): void {
    /** @var \Drupal\Core\Entity\EntityStorageInterface $storage */
    $storage = $this->container->get(EntityTypeManagerInterface::class)
      ->getStorage('node');

    $decision = $storage->create([
      'type' => 'decision',
      'title' => 'Test decision',
      'field_diary_number' => 'test-diary-number',
      'field_meeting_id' => '123',
      // Decision query expects that native id is wrapped with { }.
      'field_decision_native_id' => '{test-native-id}',
      'field_dm_org_name' => 'test-dm-org-name',
      'field_policymaker_id' => '123',
      'field_decision_attachments' => file_get_contents(__DIR__ . '/../../../fixtures/decision-attachments.json'),
    ]);
    $decision->save();

    $policymaker = $storage->create([
      'type' => 'policymaker',
      'status' => '1',
      'langcode' => 'en',
      'title' => 'Test policymaker',
      'field_policymaker_id' => '123',
    ]);
    $policymaker->save();

    $url = Url::fromRoute('paatokset_ahjo_api.case_ajax', [
      'case_id' => 'test-diary-number',
      'decision' => 'test-native-id',
    ]);

    $request = $this->getMockedRequest($url->toString());
    $response = $this->processRequest($request);
    $this->assertEquals(200, $response->getStatusCode());

    $body = json_decode($response->getContent(), TRUE);
    $this->assertNotEmpty($body['attachments']);
  }

}
