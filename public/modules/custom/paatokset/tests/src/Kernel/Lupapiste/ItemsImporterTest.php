<?php

declare(strict_types=1);

namespace Drupal\Tests\paatokset\Kernel\Lupapiste;

use Drupal\KernelTests\KernelTestBase;
use Drupal\paatokset\Lupapiste\ItemsImporter;
use Drupal\Tests\helfi_api_base\Traits\ApiTestTrait;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Tests items storage.
 *
 * @coversDefaultClass \Drupal\paatokset\Lupapiste\ItemsImporter
 */
class ItemsImporterTest extends KernelTestBase {

  use ApiTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'serialization',
    'paatokset',
  ];

  /**
   * Tests fetch() with failing request.
   */
  public function testFetchInvalidResponse(): void {
    $response = $this->prophesize(ResponseInterface::class);
    $response->getStatusCode()->willReturn(403);

    $httpClient = $this->createMockHttpClient([
      new ClientException(
        'Test',
        $this->prophesize(RequestInterface::class)->reveal(),
        $response->reveal(),
      ),
    ]);
    $importer = new ItemsImporter($httpClient);
    $this->assertEmpty($importer->fetch('fi'));
  }

  /**
   * Tests fetch().
   */
  public function testFetch(): void {
    $httpClient = $this->createMockHttpClient([
      new Response(body: $this->getFixture('paatokset', 'rss_fi.xml')),
    ]);
    $importer = new ItemsImporter($httpClient);
    $expected = [
      'toimenpideteksti' => 'fi Asuinkerrostalon tai rivitalon rakentaminen',
      'lupatunnus' => 'LP-049-2025-90341',
      'rakennuspaikka' => 'Miniatontie 12',
      'julkaisuAlkaa' => 'Thu, 13 Mar 2025 00:00:00 +0200',
      'julkaisuPaattyy' => 'Tue, 22 Apr 2025 23:59:59 +0300',
      'kiinteistotunnus' => '04903300810006',
      'paatosPvm' => 'Wed, 12 Mar 2025 00:00:00 +0200',
      'paatoksenPykala' => '13',
      'paattaja' => 'Rakennustarkastaja',
      'asiakirjaLink' => 'https://www-qa.lupapiste.fi/api/raw/download-bulletin-doc?bulletinId=LP-049-2025-90341_67d12f9be5770069ee7a654c',
      'description' => 'Rakennuslupa:
        Pientalo

        (tarvittaessa:)
        Aloittamisoikeus

        *****',
      'link' => 'https://www-qa.lupapiste.fi/app/fi/local-bulletins?organization=049-R#!/r-bulletin/LP-049-2025-90341_67d12f9be5770069ee7a654c',
      'pubDate' => 'Wed, 12 Mar 2025 08:54:15 +0200',
      'title' => 'fi Asuinkerrostalon tai rivitalon rakentaminen',
    ];

    $data = $importer->fetch('fi');
    $this->assertCount(2, $data);
    $this->assertEquals($expected, $data[0]);
  }

}
