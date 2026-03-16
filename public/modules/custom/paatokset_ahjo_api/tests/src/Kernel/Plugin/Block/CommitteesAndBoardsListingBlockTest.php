<?php

declare(strict_types=1);

namespace Drupal\Tests\paatokset_ahjo_api\Kernel\Plugin\Block;

use Drupal\paatokset_ahjo_api\Entity\OrganizationType;
use Drupal\paatokset_ahjo_api\Entity\Policymaker;
use Drupal\paatokset_ahjo_api\Entity\Sector;
use Drupal\paatokset_ahjo_api\Plugin\Block\CommitteesAndBoardsListingBlock;
use Drupal\Tests\node\Traits\NodeCreationTrait;
use Drupal\Tests\paatokset_ahjo_api\Kernel\AhjoEntityKernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Block plugin tests.
 */
#[Group('paatokset_ahjo_api')]
#[RunTestsInSeparateProcesses]
class CommitteesAndBoardsListingBlockTest extends AhjoEntityKernelTestBase {

  use NodeCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'block',
  ];

  /**
   * Creates block under test.
   */
  private function createBlock(): CommitteesAndBoardsListingBlock {
    $plugin_definition = ['provider' => 'paatokset_ahjo_api'];
    return CommitteesAndBoardsListingBlock::create(
      $this->container,
      [],
      'committees_and_boards_listing',
      $plugin_definition,
    );
  }

  /**
   * Create a policymaker node.
   */
  private function createPolicymaker(string $title, Sector $sector, OrganizationType $orgType, array $overrides = []): Policymaker {
    $node = $this->createNode([
      'type' => 'policymaker',
      'title' => $title,
      'status' => 1,
      'field_policymaker_existing' => 1,
      'field_ahjo_title' => $title,
      'field_city_council_division' => 0,
      'field_organization_type_id' => $orgType->value,
      'field_dm_sector' => json_encode(['SectorID' => $sector->value]),
      ...$overrides,
    ]);

    assert($node instanceof Policymaker);

    return $node;
  }

  /**
   * Tests block output.
   */
  public function testBlock(): void {
    // Create policymakers in two sectors, in non-alphabetical order.
    $this->createPolicymaker('Urban Committee', Sector::UrbanEnvironmentDivision, OrganizationType::COMMITTEE);
    $this->createPolicymaker('Central Board', Sector::CentralAdministration, OrganizationType::DIVISION);
    $this->createPolicymaker('Education Committee', Sector::EducationDivision, OrganizationType::COMMITTEE);
    // BOARD type (2) should be excluded — only DIVISION (4) and COMMITTEE (5).
    $this->createPolicymaker('Board', Sector::EducationDivision, OrganizationType::BOARD);

    $build = $this->createBlock()->build();
    $items = $build['#committees_and_boards'];

    $this->assertCount(3, $items);

    // Verify alphabetical sector sorting.
    $sectorLabels = array_map(
      static fn(array $item) => (string) $item['sector']->getLabel(),
      $items,
    );

    $expected = $sectorLabels;
    sort($expected);
    $this->assertSame($expected, $sectorLabels);

    // Verify the actual sectors.
    $this->assertSame(Sector::CentralAdministration, $items[0]['sector']);
    $this->assertSame(Sector::EducationDivision, $items[1]['sector']);
    $this->assertSame(Sector::UrbanEnvironmentDivision, $items[2]['sector']);
  }

}
