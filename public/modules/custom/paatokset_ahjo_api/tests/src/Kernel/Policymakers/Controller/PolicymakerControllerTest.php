<?php

declare(strict_types=1);

namespace Drupal\Tests\paatokset_ahjo_api\Kernel\Policymakers\Controller;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\file\Entity\File;
use Drupal\media\Entity\Media;
use Drupal\paatokset_ahjo_api\Entity\Meeting;
use Drupal\paatokset_ahjo_api\Entity\Policymaker;
use Drupal\paatokset_ahjo_api\Policymakers\Controller\PolicymakerController;
use Drupal\paatokset_ahjo_api\Service\OrganizationPathBuilder;
use Drupal\paatokset_ahjo_api\Service\PolicymakerService;
use Drupal\Tests\paatokset_ahjo_api\Kernel\AhjoKernelTestBase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Tests policymaker controller.
 */
class PolicymakerControllerTest extends AhjoKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'paatokset_ahjo_api',
    'ahjo_media_test',
    'image',
    'media',
  ];

  /**
   * The controller under test.
   */
  protected PolicymakerController $controller;

  /**
   * The mocked policymaker service.
   */
  protected PolicymakerService|MockObject $policymakerService;

  /**
   * The mocked organization path builder.
   */
  protected OrganizationPathBuilder|MockObject $organizationPathBuilder;

  /**
   * {@inheritDoc}
   */
  public function setUp(): void {
    parent::setUp();

    $this->installSchema('file', ['file_usage']);
    $this->installEntitySchema('file');
    $this->installEntitySchema('media');
    $this->installConfig('ahjo_media_test');

    $this->policymakerService = $this->createMock(PolicymakerService::class);
    $this->organizationPathBuilder = $this->createMock(OrganizationPathBuilder::class);

    $this->controller = new PolicymakerController(
      $this->policymakerService,
      $this->organizationPathBuilder
    );
  }

  /**
   * Tests the document route.
   */
  public function testDocumentRoutes(): void {
    $policymaker = Policymaker::create([
      'title' => 'Test policymaker',
      'field_policymaker_id' => '123',
      'field_documents_description' => 'Test description 1',
      'field_decisions_description' => 'Test description 2',
    ]);

    $this->policymakerService
      ->expects($this->atLeastOnce())
      ->method('getPolicymaker')
      ->willReturn($policymaker);

    $build = $this->controller->documents();
    $this->assertIsArray($build);
    $this->assertStringContainsString('Test description 1', $build['content']['description']['#markup']);

    $build = $this->controller->decisions();
    $this->assertIsArray($build);
    $this->assertStringContainsString('Test description 2', $build['content']['description']['#markup']);
  }

  /**
   * Tests minutes route.
   */
  public function testMinutesRoute(): void {
    $meeting = Meeting::create([
      'type' => 'meeting',
      'title' => 'Test meeting',
      'field_meeting_id' => '123',
      'field_meeting_dm_id' => '234',
      'field_meeting_date' => '2025-10-07T00:00:00',
    ]);

    $policymaker = Policymaker::create([
      'type' => 'meeting',
      'title' => 'Test policymaker',
      'field_policymaker_id' => '234',
    ]);

    $meeting->save();
    $policymaker->save();

    // Missing minutes and agenda.
    $build = $this->controller->minutes($meeting->getAhjoId());
    $this->assertArrayHasKeys(['#theme'], $build);

    file_put_contents('public://test-file.pdf', str_repeat('t', 10));
    $file = File::create([
      'uri' => 'public://test-file.pdf',
      'filename' => 'test-file.pdf',
    ]);
    $file->save();
    $minutes = Media::create([
      'bundle' => 'minutes_of_the_discussion',
      'field_meetings_reference' => $meeting->id(),
      'field_document' => $file->id(),
    ]);
    $minutes->save();

    // Missing agenda, has minutes.
    $build = $this->controller->minutes($meeting->getAhjoId());
    $this->assertArrayHasKeys(['#theme', 'minutes_of_discussion'], $build);
    $this->assertStringContainsString('test-file.pdf', $build['minutes_of_discussion'][0]['link'] ?? '');

    $meeting = Meeting::create([
      'type' => 'meeting',
      'title' => 'Test meeting',
      'field_meeting_id' => '321',
      'field_meeting_dm_id' => '234',
      'field_meeting_date' => '2025-10-07T00:00:00',
    ]);
    $meeting->save();

    $this->policymakerService
      ->expects($this->once())
      ->method('getMeetingAgenda')
      ->willReturn([
        'meeting' => 'a',
        'meeting_metadata' => 'b',
        'decision_announcement' => 'c',
        'list' => 'd',
        'file' => 'e',
      ]);

    // Has agenda, missing minutes.
    $build = $this->controller->minutes($meeting->getAhjoId());
    $this->assertArrayHasKeys(
      ['#theme', 'meeting', 'list', 'file', '#documents_description', 'decision_announcement', 'meeting_metadata'],
      $build
    );
  }

  /**
   * Assert that array has keys.
   *
   * @param array $expected
   *   Expected keys.
   * @param mixed $actual
   *   Actual array.
   * @param array $filter
   *   Keys to filter out from comparison if they provide no value to the test.
   */
  private function assertArrayHasKeys(array $expected, mixed $actual, array $filter = ['#cache']): void {
    $this->assertIsArray($actual);
    $this->assertEquals(
      $expected,
      array_filter(array_keys($actual), fn ($key) => !in_array($key, $filter))
    );
  }

  /**
   * Tests minutes route.
   *
   * @param array $nodes
   *   Nodes to create before running the test.
   */
  #[DataProvider('minutesRouteErrorsData')]
  public function testErrorsMinutesRoute(array $nodes): void {
    $storage = $this->container
      ->get(EntityTypeManagerInterface::class)
      ->getStorage('node');

    foreach ($nodes as $node) {
      $storage
        ->create($node)
        ->save();
    }

    $this->expectException(NotFoundHttpException::class);
    $this->controller->minutes('123');
  }

  /**
   * Data provider for testMinutesRouteErrors.
   */
  public static function minutesRouteErrorsData(): array {
    return [
      // Meeting not found.
      [
        [],
      ],
      // Policymaker not found.
      [
        [
          [
            'type' => 'meeting',
            'title' => 'Test meeting',
            'field_meeting_id' => '123',
            'field_meeting_dm_id' => '234',
          ],
        ],
      ],
    ];
  }

}
