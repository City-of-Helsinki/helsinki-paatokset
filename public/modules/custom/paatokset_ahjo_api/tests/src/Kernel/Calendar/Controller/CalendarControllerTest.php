<?php

declare(strict_types=1);

namespace Drupal\Tests\paatokset_ahjo_api\Kernel\Calendar\Controller;

use Drupal\Core\Cache\CacheableJsonResponse;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Url;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;
use Drupal\Tests\helfi_api_base\Traits\ApiTestTrait;
use Drupal\Tests\paatokset_ahjo_api\Kernel\AhjoEntityKernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\Response;

/**
 * Tests meeting controller.
 */
#[Group('paatokset_ahjo_api')]
#[RunTestsInSeparateProcesses]
class CalendarControllerTest extends AhjoEntityKernelTestBase {

  use ApiTestTrait;

  /**
   * Tests meetings endpoint.
   */
  public function testMeetingsEndpoint(): void {
    $storage = $this->container->get(EntityTypeManagerInterface::class)
      ->getStorage('node');

    $storage->create([
      'type' => 'policymaker',
      'status' => 1,
      'title' => 'Test policymaker',
      'field_policymaker_id' => 'dm-1',
      'field_organization_type' => 'Lautakunta',
    ])->save();

    $minutes = json_encode(['Type' => 'pöytäkirja', 'NativeId' => 'minutes', 'Language' => 'fi']);
    $agenda = json_encode(['Type' => 'esityslista', 'NativeId' => 'agenda', 'Language' => 'fi']);

    $meetings = [
      'past' => ['-10 days', 'pidetty', [$minutes]],
      'past_cancelled' => ['-5 days', 'peruttu', []],
      'agenda' => ['+2 days', 'vahvistettu', [$agenda]],
      'decision' => ['+3 days', 'pidetty', [$agenda], ['field_meeting_decision' => '<p>Decision</p>']],
      'cancelled' => ['+4 days', 'peruttu', [$agenda]],
      'moved' => ['+5 days', 'vahvistettu', [], ['field_meeting_date_original' => $this->storageDate('+6 days')]],
      'other_policymaker' => ['+6 days', 'vahvistettu', [], ['field_meeting_dm_id' => 'dm-2']],
      'unpublished' => ['+7 days', 'vahvistettu', [], ['status' => 0]],
      'out_of_range' => ['-60 days', 'pidetty', []],
    ];

    foreach ($meetings as $title => [$date, $status, $documents]) {
      $storage->create([
        'type' => 'meeting',
        'status' => 1,
        'title' => $title,
        'field_meeting_id' => "meeting-$title",
        'field_meeting_dm_id' => 'dm-1',
        'field_meeting_date' => $this->storageDate($date),
        'field_meeting_status' => $status,
        'field_meeting_documents' => $documents,
        ...($meetings[$title][3] ?? []),
      ])->save();
    }

    $response = $this->request(['from' => date('Y-m-d', strtotime('-30 days'))]);
    $this->assertEquals(200, $response->getStatusCode());
    $this->assertInstanceOf(CacheableJsonResponse::class, $response);
    $this->assertContains('node_list:meeting', $response->getCacheableMetadata()->getCacheTags());

    $data = json_decode((string) $response->getContent(), TRUE)['data'];
    $items = array_column(array_merge(...array_values($data)), NULL, 'title');

    // Sorted by date, past cancelled, unpublished and out of range
    // meetings are excluded.
    $this->assertSame(['past', 'agenda', 'decision', 'cancelled', 'moved', 'other_policymaker'], array_keys($items));

    // Grouped by date.
    $this->assertSame('past', $data[date('Y-m-d', strtotime('-10 days'))][0]['title']);

    $this->assertSame('minutes', $items['past']['phase']);
    $this->assertSame('lautakunta', $items['past']['policymaker_type']);
    $this->assertSame('Test policymaker', $items['past']['policymaker_name']);
    $this->assertStringContainsString('meeting-past', $items['past']['minutes_link']);

    $this->assertSame('agenda', $items['agenda']['phase']);
    $this->assertStringContainsString('meeting-agenda', $items['agenda']['motions_list_link']);

    $this->assertSame('decision', $items['decision']['phase']);
    $this->assertStringContainsString('meeting-decision', $items['decision']['decision_link']);

    $this->assertTrue($items['cancelled']['meeting_cancelled']);
    $this->assertSame('Meeting cancelled', $items['cancelled']['additional_info']);
    $this->assertArrayNotHasKey('motions_list_link', $items['cancelled']);

    $this->assertTrue($items['moved']['meeting_moved']);
    $this->assertSame('Meeting moved', $items['moved']['additional_info']);
    $this->assertSame(date('d.m. H:i', strtotime('+6 days')), $items['moved']['orig_time']);

    // Policymaker without node.
    $this->assertArrayNotHasKey('policymaker_name', $items['other_policymaker']);

    // Policymaker and date range filters.
    $response = $this->request([
      'from' => date('Y-m-d', strtotime('-30 days')),
      'to' => date('Y-m-d', strtotime('+3 days')),
      'policymaker' => 'dm-1',
    ]);
    $data = json_decode((string) $response->getContent(), TRUE)['data'];
    $this->assertSame(['past', 'agenda'], array_column(array_merge(...array_values($data)), 'title'));

    $response = $this->request(['policymaker' => 'dm-2']);
    $data = json_decode((string) $response->getContent(), TRUE)['data'];
    $this->assertSame(['other_policymaker'], array_column(array_merge(...array_values($data)), 'title'));

    // Invalid date.
    $response = $this->request(['from' => 'not-a-date']);
    $this->assertEquals(400, $response->getStatusCode());
  }

  /**
   * Makes request to meetings endpoint.
   *
   * @param array<string, string> $query
   *   Query parameters.
   */
  private function request(array $query): Response {
    $url = Url::fromRoute('ahjo_meeting_endpoint', options: ['query' => $query]);
    return $this->processRequest($this->getMockedRequest($url->toString()));
  }

  /**
   * Gets relative date in datetime field storage format.
   */
  private function storageDate(string $relative): string {
    return (new \DateTime($relative, new \DateTimeZone('UTC')))
      ->format(DateTimeItemInterface::DATETIME_STORAGE_FORMAT);
  }

}
