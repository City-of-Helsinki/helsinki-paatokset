<?php

declare(strict_types=1);

namespace Drupal\Tests\paatokset_ahjo_api\Kernel\Controller;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Url;
use Drupal\Tests\helfi_api_base\Traits\ApiTestTrait;
use Drupal\Tests\paatokset_ahjo_api\Kernel\AhjoEntityKernelTestBase;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Tests case controller.
 */
class CaseControllerTest extends AhjoEntityKernelTestBase {

  use ApiTestTrait;
  use UserCreationTrait;

  /**
   * Tests case ajax endpoint.
   */
  public function testCaseAjaxEndpoint(): void {
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

  /**
   * Tests ::view redirects to decision when query parameter is set.
   */
  public function testRedirectsToDecision(): void {
    // Setup permissions.
    $this->setUpCurrentUser(permissions: ['view remote entities']);

    /** @var \Drupal\Core\Entity\EntityStorageInterface $nodeStorage */
    $nodeStorage = $this->container->get(EntityTypeManagerInterface::class)
      ->getStorage('node');

    /** @var \Drupal\Core\Entity\EntityStorageInterface $caseStorage */
    $caseStorage = $this->container->get(EntityTypeManagerInterface::class)
      ->getStorage('ahjo_case');

    $case = $caseStorage->create([
      'id' => 'HEL-2025-000001',
      'label' => 'HEL 2025-000001',
      'title' => 'Test case',
      'status' => 1,
    ]);
    $case->save();

    $decision = $nodeStorage->create([
      'type' => 'decision',
      'title' => 'Test decision',
      'field_diary_number' => 'HEL-2025-000001',
      'field_meeting_id' => '123',
      'field_decision_native_id' => '{test-native-id}',
      'field_dm_org_name' => 'test-dm-org-name',
      'field_policymaker_id' => '123',
    ]);
    $decision->save();

    // Request case view with decision query parameter.
    $url = Url::fromRoute('entity.ahjo_case.canonical', ['ahjo_case' => $case->id()], options: [
      'query' => ['decision' => 'test-native-id'],
    ]);

    $request = $this->getMockedRequest($url->toString());
    $response = $this->processRequest($request);

    $this->assertInstanceOf(RedirectResponse::class, $response);
    $this->assertEquals(302, $response->getStatusCode());
    $this->assertStringContainsString('/node/', $response->getTargetUrl());

    $case = $caseStorage->create([
      'id' => 'HEL-2025-000002',
      'label' => 'HEL 2025-000002',
      'title' => 'Test case',
      'status' => 1,
    ]);
    $case->save();

    // View throws 404 when decision query parameter doesn't match.
    $url = Url::fromRoute('entity.ahjo_case.canonical', ['ahjo_case' => $case->id()], options: [
      'query' => ['decision' => 'does-not-exist'],
    ]);

    $request = $this->getMockedRequest($url->toString());
    $response = $this->processRequest($request);
    $this->assertEquals(404, $response->getStatusCode());
  }

  /**
   * Tests view renders case when it has a default decision.
   */
  public function testViewRendersCase(): void {
    // Setup permissions.
    $this->setUpCurrentUser(permissions: ['view remote entities']);

    /** @var \Drupal\Core\Entity\EntityStorageInterface $nodeStorage */
    $nodeStorage = $this->container->get(EntityTypeManagerInterface::class)
      ->getStorage('node');

    /** @var \Drupal\Core\Entity\EntityStorageInterface $caseStorage */
    $caseStorage = $this->container->get(EntityTypeManagerInterface::class)
      ->getStorage('ahjo_case');

    $case = $caseStorage->create([
      'id' => 'HEL-2025-000004',
      'label' => 'HEL 2025-000004',
      'title' => 'Test case with decision',
      'status' => 1,
    ]);
    $case->save();

    $decision = $nodeStorage->create([
      'type' => 'decision',
      'title' => 'Test decision',
      'field_diary_number' => 'HEL-2025-000004',
      'field_meeting_id' => '456',
      'field_decision_native_id' => '{another-native-id}',
      'field_dm_org_name' => 'test-dm-org-name',
      'field_policymaker_id' => '123',
    ]);
    $decision->save();

    // Request case view without query parameter.
    $url = Url::fromRoute('entity.ahjo_case.canonical', [
      'ahjo_case' => $case->id(),
    ]);

    $request = $this->getMockedRequest($url->toString());
    $response = $this->processRequest($request);

    $this->assertEquals(200, $response->getStatusCode());
  }

}
