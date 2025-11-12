<?php

declare(strict_types=1);

namespace Drupal\Tests\paatokset\Unit\Plugin\DebugDataItem;

use Drupal\paatokset\Lupapiste\ItemsImporter;
use Drupal\paatokset\Plugin\DebugDataItem\LupapisteApiAvailability;
use Drupal\Tests\helfi_api_base\Traits\ApiTestTrait;
use Drupal\Tests\UnitTestCase;
use GuzzleHttp\Exception\TransferException;
use GuzzleHttp\Psr7\Response;
use Laminas\Feed\Exception\InvalidArgumentException;
use Prophecy\PhpUnit\ProphecyTrait;

/**
 * @coversDefaultClass \Drupal\paatokset\Plugin\DebugDataItem\LupapisteApiAvailability
 * @group paatokset
 */
class LupapisteApiAvailabilityTest extends UnitTestCase {

  use ProphecyTrait;
  use ApiTestTrait;

  /**
   * Gets the SUT.
   *
   * @param array $responses
   *   The expected responses.
   *
   * @return \Drupal\paatokset\Plugin\DebugDataItem\LupapisteApiAvailability
   *   The SUT.
   */
  public function getSut(array $responses): LupapisteApiAvailability {
    $httpClient = $this->createMockHttpClient($responses);
    $importer = new ItemsImporter($httpClient);

    return new LupapisteApiAvailability([], '', [], $importer);
  }

  /**
   * Tests a failed check().
   */
  public function testFailedCheck(): void {
    $responses = [
      new TransferException(),
      new InvalidArgumentException(),
      new Response(body: ''),
    ];
    $sut = $this->getSut($responses);

    for ($i = 0; $i < count($responses); $i++) {
      $this->assertFalse($sut->check());
    }
  }

  /**
   * Test successful check().
   */
  public function testCheck(): void {
    $fixture = file_get_contents(__DIR__ . '/../../../../fixtures/rss_fi.xml');
    $sut = $this->getSut([
      new Response(body: $fixture),
    ]);
    $this->assertTrue($sut->check());
  }

}
