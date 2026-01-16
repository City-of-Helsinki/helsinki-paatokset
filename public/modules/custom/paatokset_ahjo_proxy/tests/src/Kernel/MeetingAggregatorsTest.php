<?php

declare(strict_types=1);

namespace Drupal\Tests\paatokset_ahjo_proxy\Kernel;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\node\NodeInterface;
use Drupal\paatokset_ahjo_proxy\AhjoBatchBuilder;
use Drupal\Tests\helfi_api_base\Traits\ApiTestTrait;
use Drupal\Tests\node\Traits\NodeCreationTrait;
use Drupal\Tests\paatokset_ahjo_api\Kernel\AhjoEntityKernelTestBase;
use GuzzleHttp\Psr7\Response;

/**
 * Tests aggregator commands for meetings.
 *
 * @group paatokset_ahjo_api
 */
class MeetingAggregatorsTest extends AhjoEntityKernelTestBase {

  use NodeCreationTrait;
  use ApiTestTrait;

  /**
   * {@inheritDoc}
   */
  protected static $modules = [
    'paatokset_ahjo_proxy',
  ];

  /**
   * Tests ahjo-proxy:get-motions command batch creation.
   *
   * Meeting lifecycle:
   * Cron job runs ahjo-proxy:get-motions every few minutes, which
   * generates motions from meeting data.
   */
  public function testMotionsFromAgendaItems(): void {
    $this->container->set('http_client', $this->createMockHttpClient([
      // Decision response.
      new Response(200, body: file_get_contents(__DIR__ . '/../../fixtures/decision.json')),
      // Agenda item response?
      new Response(200, body: '{}'),
    ]));

    // Create unprocessed meeting.
    $meeting = $this->createNode([
      'type' => 'meeting',
      'status' => NodeInterface::PUBLISHED,
      'field_meeting_id' => 'U540202024',
      'field_meeting_sequence_number' => 24,
      'field_meeting_date' => '2020-09-01T12:30:00',
      'field_meeting_agenda_published' => 1,
      'field_meeting_dm_id' => 'U540',
      'field_meeting_dm' => 'Kaupunkiympäristölautakunta',
      'field_meeting_minutes_published' => 0,
      'field_agenda_items_processed' => 0,
      'field_meeting_agenda' => [
        json_encode([
          "AgendaItem" => "Kaupunkiympäristölautakunnan esitys kaupunginhallitukselle Elielinaukion kehittämisen aiesopimuksen hyväksymiseksi",
          "AgendaPoint" => 3,
          "PDF" => [
            "Type" => "päätös",
            "NativeId" => "{8E100ECE-7BE1-46A7-8D53-C49F02B762D3}",
            "PublicityClass" => "Julkinen",
            "Language" => "fi",
            "VersionSeriesId" => "{DFB58794-34EC-C90D-BBC7-7453C0400008}",
          ],
          "HTML" => file_get_contents(__DIR__ . '/../../../../paatokset_ahjo_api/tests/fixtures/meeting.html'),
        ]),
      ],
    ]);

    $batch = $this->container
      ->get(AhjoBatchBuilder::class)
      ->getMotionsFromAgendaItemsBatch();

    // Batch should have one operation.
    $this->assertCount(1, $batch->toArray()['operations']);

    $meeting = $this->container
      ->get(EntityTypeManagerInterface::class)
      ->getStorage('node')
      ->load($meeting->id());

    $this->assertNotEmpty($meeting);

    // Agenda items are marked as processed.
    $this->assertTrue((bool) $meeting->field_agenda_items_processed->value);

    $context = [];
    foreach ($batch->toArray()['operations'] as $operation) {
      [$callback, $data] = $operation;
      call_user_func_array($callback, [...$data, &$context]);
    }

    // One item was processed.
    $this->assertCount(1, $context['results']['items']);

    // Load decision that was just created.
    $decisions = $this->container
      ->get(EntityTypeManagerInterface::class)
      ->getStorage('node')
      ->loadByProperties([
        'field_unique_id' => 'HEL-2020-008126-U540202024-0-3-U540',
      ]);

    $this->assertCount(1, $decisions);
    $decision = reset($decisions);

    $this->assertEquals("decision", $decision->getType());

  }

}
