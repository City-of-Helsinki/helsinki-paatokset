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

    // All relative dates are computed from the same base time, so
    // the test does not break if the clock ticks over mid-test.
    $now = time();

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
      'moved' => ['+5 days', 'vahvistettu', [], ['field_meeting_date_original' => $this->storageDate('+6 days', $now)]],
      'other_policymaker' => ['+6 days', 'vahvistettu', [], ['field_meeting_dm_id' => 'dm-2']],
      'unpublished' => ['+7 days', 'vahvistettu', [], ['status' => 0]],
      'out_of_range_past' => ['-120 days', 'pidetty', []],
      'out_of_range_future' => ['+150 days', 'vahvistettu', []],
    ];

    foreach ($meetings as $title => [$date, $status, $documents]) {
      $storage->create([
        'type' => 'meeting',
        'status' => 1,
        'title' => $title,
        'field_meeting_id' => "meeting-$title",
        'field_meeting_dm_id' => 'dm-1',
        'field_meeting_date' => $this->storageDate($date, $now),
        'field_meeting_status' => $status,
        'field_meeting_documents' => $documents,
        ...($meetings[$title][3] ?? []),
      ])->save();
    }

    // Range starting from previous month covers all fixtures
    // except the out of range ones regardless of the current date.
    $response = $this->request(['month' => date('Y-m', strtotime('first day of previous month', $now))]);
    $this->assertEquals(200, $response->getStatusCode());
    $this->assertInstanceOf(CacheableJsonResponse::class, $response);
    $this->assertContains('node_list:meeting', $response->getCacheableMetadata()->getCacheTags());

    $data = json_decode((string) $response->getContent(), TRUE)['data'];
    $items = array_column(array_merge(...array_values($data)), NULL, 'title');

    // Sorted by date, past cancelled, unpublished and out of range
    // meetings are excluded.
    $this->assertSame(['past', 'agenda', 'decision', 'cancelled', 'moved', 'other_policymaker'], array_keys($items));

    // Grouped by date.
    $this->assertSame('past', $data[date('Y-m-d', strtotime('-10 days', $now))][0]['title']);

    $this->assertSame('minutes', $items['past']['phase']);
    $this->assertSame('lautakunta', $items['past']['policymakerType']);
    $this->assertSame('Test policymaker', $items['past']['policymakerName']);
    $this->assertStringContainsString('meeting-past', $items['past']['minutesLink']);

    $this->assertSame('agenda', $items['agenda']['phase']);
    $this->assertStringContainsString('meeting-agenda', $items['agenda']['motionsListLink']);

    $this->assertSame('decision', $items['decision']['phase']);
    $this->assertStringContainsString('meeting-decision', $items['decision']['decisionLink']);

    $this->assertTrue($items['cancelled']['meetingCancelled']);
    $this->assertSame('Meeting cancelled', $items['cancelled']['additionalInfo']);
    $this->assertNull($items['cancelled']['motionsListLink']);

    $this->assertTrue($items['moved']['meetingMoved']);
    $this->assertSame('Meeting moved', $items['moved']['additionalInfo']);
    $this->assertSame(date('d.m. H:i', strtotime('+6 days', $now)), $items['moved']['origTime']);

    // Policymaker without node.
    $this->assertNull($items['other_policymaker']['policymakerName']);

  }

  /**
   * Tests that the endpoint returns three months padded with a week.
   */
  public function testMeetingsEndpointRange(): void {
    $storage = $this->container->get(EntityTypeManagerInterface::class)
      ->getStorage('node');

    $meetings = [
      'before_padding' => '2019-12-20T10:00:00',
      'leading_padding' => '2019-12-28T10:00:00',
      'first_month' => '2020-01-15T10:00:00',
      'last_month' => '2020-03-31T10:00:00',
      'trailing_padding' => '2020-04-05T10:00:00',
      'after_padding' => '2020-04-12T10:00:00',
    ];

    foreach ($meetings as $title => $date) {
      $storage->create([
        'type' => 'meeting',
        'status' => 1,
        'title' => $title,
        'field_meeting_id' => "meeting-$title",
        'field_meeting_dm_id' => 'dm-1',
        'field_meeting_date' => $date,
        'field_meeting_status' => 'pidetty',
      ])->save();
    }

    $response = $this->request(['month' => '2020-01']);
    $this->assertEquals(200, $response->getStatusCode());

    $data = json_decode((string) $response->getContent(), TRUE)['data'];
    $titles = array_column(array_merge(...array_values($data)), 'title');
    $this->assertSame(['leading_padding', 'first_month', 'last_month', 'trailing_padding'], $titles);

    // Missing or invalid month.
    $invalid = [
      [],
      ['from' => '1900-01-01'],
      ['month' => 'foo'],
      ['month' => '2020-13'],
      ['month' => '2020-1'],
      ['month' => '2020-01-01'],
    ];
    foreach ($invalid as $query) {
      $response = $this->request($query);
      $this->assertEquals(400, $response->getStatusCode(), json_encode($query) ?: '');
    }
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
   *
   * @param string $relative
   *   Relative date, e.g. '+6 days'.
   * @param int $now
   *   Base timestamp the relative date is computed from.
   */
  private function storageDate(string $relative, int $now): string {
    return (new \DateTimeImmutable('@' . $now))
      ->modify($relative)
      ->format(DateTimeItemInterface::DATETIME_STORAGE_FORMAT);
  }

}
