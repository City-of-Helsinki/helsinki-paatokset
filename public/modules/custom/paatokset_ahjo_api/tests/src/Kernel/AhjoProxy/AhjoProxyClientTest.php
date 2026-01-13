<?php

declare(strict_types=1);

namespace Drupal\Tests\paatokset_ahjo_api\Kernel\AhjoProxy;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\helfi_api_base\Environment\EnvironmentEnum;
use Drupal\helfi_api_base\Environment\Project;
use Drupal\KernelTests\KernelTestBase;
use Drupal\paatokset_ahjo_api\AhjoProxy\AhjoProxyClient;
use Drupal\paatokset_ahjo_api\AhjoProxy\AhjoProxyClientInterface;
use Drupal\paatokset_ahjo_api\AhjoProxy\AhjoProxyException;
use Drupal\paatokset_ahjo_api\AhjoProxy\DTO\AhjoCase;
use Drupal\paatokset_ahjo_api\AhjoProxy\DTO\AhjoHandling;
use Drupal\paatokset_ahjo_api\AhjoProxy\DTO\AhjojulkaisuDocument;
use Drupal\paatokset_ahjo_api\AhjoProxy\DTO\AhjoRecord;
use Drupal\paatokset_ahjo_api\AhjoProxy\DTO\Chairmanship;
use Drupal\paatokset_ahjo_api\AhjoProxy\DTO\Organization;
use Drupal\paatokset_ahjo_api\AhjoProxy\DTO\OrganizationNode;
use Drupal\paatokset_ahjo_api\AhjoProxy\DTO\Trustee;
use Drupal\paatokset_ahjo_api\Entity\OrganizationType;
use Drupal\Tests\helfi_api_base\Traits\ApiTestTrait;
use Drupal\Tests\helfi_api_base\Traits\EnvironmentResolverTrait;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;

/**
 * Kernel tests for Ahjo proxy client.
 */
class AhjoProxyClientTest extends KernelTestBase {

  use ApiTestTrait;
  use EnvironmentResolverTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'helfi_api_base',
    'paatokset_ahjo_api',
    'path_alias',
    'pathauto',
    'token',
  ];

  /**
   * {@inheritDoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this
      ->config('paatokset_ahjo_api.settings')
      ->set('proxy_api_key', '123')
      ->save();
  }

  /**
   * Tests ahjo proxy client trustees.
   */
  public function testTrusteeClient(): void {
    $sut = $this->getSut([
      new Response(200, [], file_get_contents(dirname(__DIR__, 3) . '/fixtures/trustee.json')),
      new ClientException('test-error', new Request('GET', '/test'), new Response()),
    ]);

    $trustee = $sut->getTrustee('fi', 'test-trustee');
    $this->assertInstanceOf(Trustee::class, $trustee);

    // Hard coded to match trustee.json file.
    $this->assertCount(1, $trustee->initiatives);
    $this->assertCount(1, $trustee->resolutions);
    $this->assertCount(1, $trustee->chairmanships);
    $this->assertEquals('test-trustee', $trustee->id);

    foreach ([...$trustee->initiatives, ...$trustee->resolutions] as $initiative) {
      $this->assertInstanceOf(AhjojulkaisuDocument::class, $initiative);
    }

    foreach ($trustee->chairmanships as $chairmanship) {
      $this->assertInstanceOf(Chairmanship::class, $chairmanship);
    }

    $this->expectException(AhjoProxyException::class);
    $sut->getTrustee('fi', 'test-trustee');
  }

  /**
   * Tests ahjo proxy client organizations.
   */
  public function testOrganizationClient(): void {
    $sut = $this->getSut([
      new Response(200, [], file_get_contents(dirname(__DIR__, 3) . '/fixtures/organizations-00001.json')),
      new Response(200, [], file_get_contents(dirname(__DIR__, 3) . '/fixtures/organizations-02900.json')),
      new ClientException('test-error', new Request('GET', '/test'), new Response()),
    ]);

    $organization = $sut->getOrganization('fi', '00001');
    $this->assertInstanceOf(OrganizationNode::class, $organization);

    // Hard coded to match the json file.
    $this->assertCount(1, $organization->children);
    $this->assertNull($organization->parent);
    $this->assertEquals('00001', $organization->organization->id);
    $this->assertEquals(OrganizationType::CITY, $organization->organization->type);
    $this->assertEquals('Helsingin kaupunki', $organization->organization->name);
    $this->assertCount(1, $organization->children);

    foreach ($organization->children as $child) {
      $this->assertInstanceOf(Organization::class, $child);
    }

    $organization = $sut->getOrganization('fi', '02900');
    $this->assertInstanceOf(OrganizationNode::class, $organization);

    // Hard coded to match the json file.
    $this->assertCount(1, $organization->children);
    $this->assertNotNull($organization->parent);
    $this->assertEquals('02900', $organization->organization->id);
    $this->assertEquals(OrganizationType::COUNCIL, $organization->organization->type);
    $this->assertEquals('Kaupunginvaltuusto', $organization->organization->name);
    $this->assertCount(1, $organization->children);

    foreach ($organization->children as $child) {
      $this->assertInstanceOf(Organization::class, $child);
      $this->assertFalse($child->existing);
    }

    $this->expectException(AhjoProxyException::class);
    $sut->getTrustee('fi', 'test-trustee');
  }

  /**
   * Tests ahjo proxy client cases.
   */
  public function testCaseClient(): void {
    $sut = $this->getSut([
      new Response(200, [], file_get_contents(dirname(__DIR__, 3) . '/fixtures/case.json')),
      new ClientException('test-error', new Request('GET', '/test'), new Response()),
    ]);

    $case = $sut->getCase('fi', 'HEL-2025-001216');
    $this->assertInstanceOf(AhjoCase::class, $case);

    // Hard coded to match case.json file.
    $this->assertEquals('HEL-2025-001216', $case->id);
    $this->assertEquals('HEL 2025-001216', $case->caseIdLabel);
    $this->assertEquals('Valtuustoaloite, Haltialan kotieläintilasta eläinsuojelukeskus', $case->title);
    $this->assertEquals('00 00 03', $case->classificationCode);
    $this->assertEquals('Valtuuston aloitetoiminta', $case->classificationTitle);
    $this->assertEquals('Ratkaistu', $case->status);
    $this->assertEquals('fi', $case->language);
    $this->assertEquals('Julkinen', $case->publicityClass);
    $this->assertIsArray($case->securityReasons);
    $this->assertCount(0, $case->securityReasons);

    // Test date fields.
    $this->assertInstanceOf(\DateTimeImmutable::class, $case->created);
    $this->assertEquals('2025-01-22 19:46:13', $case->created->format('Y-m-d H:i:s'));
    $this->assertInstanceOf(\DateTimeImmutable::class, $case->acquired);
    $this->assertEquals('2025-01-22 18:00:00', $case->acquired->format('Y-m-d H:i:s'));

    // Test handlings.
    $this->assertCount(2, $case->handlings);
    foreach ($case->handlings as $handling) {
      $this->assertInstanceOf(AhjoHandling::class, $handling);
      $this->assertInstanceOf(\DateTimeImmutable::class, $handling->created);
      $this->assertEquals('Keskushallinto', $handling->sector);
      $this->assertEquals('Päättynyt', $handling->status);
      $this->assertEquals('U50', $handling->sectorId);
    }

    // Test records.
    $this->assertCount(3, $case->records);
    foreach ($case->records as $record) {
      $this->assertInstanceOf(AhjoRecord::class, $record);
      $this->assertEquals('Julkinen', $record->publicityClass);
      $this->assertTrue(in_array($record->language, ['fi', 'sv']));
    }

    // Test first record with no issued date.
    $this->assertNull($case->records[0]->issued);
    $this->assertEquals('esitys', $case->records[0]->type);

    // Test second record with issued date.
    $this->assertInstanceOf(\DateTimeImmutable::class, $case->records[2]->issued);
    $this->assertEquals('2025-04-07 03:00:00', $case->records[2]->issued->format('Y-m-d H:i:s'));
    $this->assertEquals('päätös', $case->records[2]->type);

    $this->expectException(AhjoProxyException::class);
    $sut->getCase('fi', 'HEL-2025-001216');
  }

  /**
   * Tests ahjo proxy client multiple cases.
   */
  public function testGetCases(): void {
    $sut = $this->getSut([
      new Response(200, [], file_get_contents(dirname(__DIR__, 3) . '/fixtures/cases.json')),
      new Response(200, [], json_encode(['cases' => []])),
      new Response(200, [], json_encode(['invalid' => 'response'])),
    ]);

    // Test successful response with multiple cases.
    $handledBefore = new \DateTimeImmutable('2024-03-05');
    $handledAfter = new \DateTimeImmutable('2022-10-01');
    $interval = new \DateInterval('P1Y');
    $casesGenerator = $sut->getCases('fi', $handledAfter, $handledBefore, $interval);

    $this->assertInstanceOf(\Generator::class, $casesGenerator);

    // Convert generator to array for testing.
    $cases = iterator_to_array($casesGenerator);
    $this->assertCount(5, $cases);

    // Verify all items are AhjoCase instances.
    foreach ($cases as $case) {
      $this->assertInstanceOf(AhjoCase::class, $case);
    }

    // Verify first case properties (from cases.json fixture).
    $firstCase = $cases[0];
    $this->assertEquals('HEL-2022-011760', $firstCase->id);
    $this->assertEquals('HEL 2022-011760', $firstCase->caseIdLabel);
    $this->assertEquals('Lausuntopyyntö, Suomi-rata Oy:n Lentorata-hanke, ympäristövaikutusten arviointiohjelma, UUDELY', $firstCase->title);
    $this->assertEquals('11 01 05', $firstCase->classificationCode);
    $this->assertEquals('Ympäristövaikutusten arviointi', $firstCase->classificationTitle);
    $this->assertEquals('Vireillä', $firstCase->status);
    $this->assertEquals('fi', $firstCase->language);
    $this->assertEquals('Julkinen', $firstCase->publicityClass);

    // Verify date fields.
    $this->assertInstanceOf(\DateTimeImmutable::class, $firstCase->created);
    $this->assertEquals('2022-10-05', $firstCase->created->format('Y-m-d'));
    $this->assertInstanceOf(\DateTimeImmutable::class, $firstCase->acquired);

    // Verify SecurityReasons handles null (from cases.json line 19).
    $this->assertIsArray($firstCase->securityReasons);

    // Test empty response.
    $emptyCasesGenerator = $sut->getCases('fi', $handledAfter, $handledBefore, $interval);
    $emptyCases = iterator_to_array($emptyCasesGenerator);
    $this->assertCount(0, $emptyCases);

    // Test missing cases key in response.
    $this->expectException(AhjoProxyException::class);
    $this->expectExceptionMessage('Cases data not found in response.');
    iterator_to_array($sut->getCases('fi', $handledAfter, $handledBefore, $interval));
  }

  /**
   * Get service under test.
   */
  private function getSut(array $responses): AhjoProxyClientInterface {
    $environmentResolver = $this->getEnvironmentResolver(
      Project::PAATOKSET,
      EnvironmentEnum::Test->value
    );

    return new AhjoProxyClient(
      $this->createMockHttpClient($responses),
      $environmentResolver,
      $this->container->get(ConfigFactoryInterface::class),
    );
  }

}
