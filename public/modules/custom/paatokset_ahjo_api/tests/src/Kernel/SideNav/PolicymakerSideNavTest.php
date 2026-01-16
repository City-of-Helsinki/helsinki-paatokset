<?php

declare(strict_types=1);

namespace Drupal\Tests\paatokset_ahjo_api\Kernel\SideNav;

use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Url;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\paatokset_ahjo_api\Entity\Decision;
use Drupal\paatokset_ahjo_api\Entity\Policymaker;
use Drupal\paatokset_ahjo_api\Plugin\Block\PolicymakerMobileNav;
use Drupal\paatokset_ahjo_api\Plugin\Block\PolicymakerSideNav;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\paragraphs\Entity\ParagraphsType;
use Drupal\Tests\paatokset_ahjo_api\Kernel\AhjoEntityKernelTestBase;
use Symfony\Component\HttpFoundation\ParameterBag;

/**
 * Tests PolicymakerSideNav block.
 */
class PolicymakerSideNavTest extends AhjoEntityKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'paragraphs',
    'entity_reference_revisions',
  ];

  /**
   * {@inheritDoc}
   */
  public function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('paragraph');

    ParagraphsType::create([
      'id' => 'custom_content_links',
    ])->save();

    FieldStorageConfig::create([
      'field_name' => 'field_custom_menu_links',
      'entity_type' => 'node',
      'type' => 'entity_reference_revisions',
      'settings' => [
        'target_type' => 'paragraph',
      ],
    ])->save();

    FieldConfig::create([
      'field_name' => 'field_custom_menu_links',
      'entity_type' => 'node',
      'bundle' => 'policymaker',
      'label' => 'Custom menu links',
      'settings' => [
        'handler' => 'default:paragraph',
        'handler_settings' => [
          'target_bundles' => [
            'custom_content_links' => 'custom_content_links',
          ],
        ],
      ],
    ])->save();

    FieldStorageConfig::create([
      'field_name' => 'field_referenced_content',
      'entity_type' => 'paragraph',
      'type' => 'entity_reference',
    ])->save();

    FieldConfig::create([
      'field_name' => 'field_referenced_content',
      'entity_type' => 'paragraph',
      'bundle' => 'custom_content_links',
      'label' => 'Custom menu link - referenced content',
    ])->save();

    FieldStorageConfig::create([
      'field_name' => 'field_link_label',
      'entity_type' => 'paragraph',
      'type' => 'string',
    ])->save();

    FieldConfig::create([
      'field_name' => 'field_link_label',
      'entity_type' => 'paragraph',
      'bundle' => 'custom_content_links',
      'label' => 'Custom menu link - link label',
    ])->save();
  }

  /**
   * Tests the side nav block.
   */
  public function testPolicymakerSideNav() {
    $routeMatch = $this->prophesize(RouteMatchInterface::class);
    $routeMatch
      ->getParameter('node')
      ->willReturn(NULL);
    $routeMatch->getParameters()->willReturn(new ParameterBag());

    $this->container->set('current_route_match', $routeMatch->reveal());

    $sut = PolicymakerSideNav::create($this->container, [], 'policymaker_sidenav', ['provider' => 'paatokset_ahjo_api']);
    $build = $sut->build();
    $this->assertEmpty($build);

    // Inject policymaker to the current route.
    $policymaker = Policymaker::create([
      'title' => 'Kaupunginvaltuusto',
      'field_organization_type' => 'valtuusto',
    ]);
    $policymaker->save();
    $routeMatch
      ->getParameter('node')
      ->willReturn($policymaker);

    $sut = PolicymakerSideNav::create($this->container, [], 'policymaker_sidenav', ['provider' => 'paatokset_ahjo_api']);
    $build = $sut->build();
    $this->assertMenuItems([
      // Link to current policymaker.
      $policymaker->toUrl('canonical')->toString(),
      // Link to documents page.
      "/decisionmakers/{$policymaker->id()}/documents",
      // Link to discussion minutes (only available for city council).
      "/decisionmakers/{$policymaker->id()}/discussion-minutes",
    ], $build);

    // Setup custom_content_links paragraph.
    $decision = Decision::create(['title' => 'Tests']);
    $decision->save();
    $paragraph = Paragraph::create([
      'type' => 'custom_content_links',
      'field_link_label' => 'Test',
      'field_referenced_content' => [
        $decision,
      ],
    ]);
    $paragraph->save();
    $policymaker->set('field_custom_menu_links', [$paragraph]);
    $policymaker->save();

    // Custom menu link paragraph is present.
    $sut = PolicymakerSideNav::create($this->container, [], 'policymaker_sidenav', ['provider' => 'paatokset_ahjo_api']);
    $build = $sut->build();
    $this->assertEquals('policymakers.en', $build['#menu_link_parent']['url']?->getRouteName() ?? '');
    $this->assertMenuItems([
      // Link to current policymaker.
      $policymaker->toUrl('canonical')->toString(),
      // Link to documents page.
      "/decisionmakers/{$policymaker->id()}/documents",
      // Link to discussion minutes (only available for city council).
      "/decisionmakers/{$policymaker->id()}/discussion-minutes",
      // Custom link.
      $decision->toUrl('canonical')->toString(),
    ], $build);
  }

  /**
   * Tests the mobile nav block.
   */
  public function testPolicymakerMobileNav() {
    $routeMatch = $this->prophesize(RouteMatchInterface::class);
    $routeMatch
      ->getParameter('node')
      ->willReturn(NULL);
    $routeMatch->getParameters()->willReturn(new ParameterBag());

    $this->container->set('current_route_match', $routeMatch->reveal());

    $sut = PolicymakerMobileNav::create($this->container, [], 'policymaker_side_nav_mobile', ['provider' => 'paatokset_ahjo_api']);
    $build = $sut->build();
    $this->assertMenuItems([], $build);

    // Inject policymaker to the current route.
    $policymaker = Policymaker::create([
      'title' => 'Kaupunginhallitus',
      'field_organization_type' => 'hallitus',
    ]);
    $policymaker->save();
    $routeMatch
      ->getParameter('node')
      ->willReturn($policymaker);

    $sut = PolicymakerMobileNav::create($this->container, [], 'policymaker_sidenav', ['provider' => 'paatokset_ahjo_api']);
    $build = $sut->build();
    $this->assertMenuItems([
      // Link to current policymaker.
      $policymaker->toUrl('canonical')->toString(),
      // Link to documents page.
      "/decisionmakers/{$policymaker->id()}/documents",
    ], $build);
  }

  /**
   * Assert menu items.
   */
  private function assertMenuItems(array $expected, array $build): void {
    $this->assertEquals(count($expected), count($build['#items']));

    foreach ($expected as $id => $expectedItem) {
      $item = $build['#items'][$id];
      $this->assertInstanceOf(Url::class, $item['url'] ?? NULL);
      $this->assertEquals($expectedItem, $item['url']->toString());
    }
  }

}
