<?php

declare(strict_types=1);

namespace Drupal\Tests\paatokset_ahjo_api\Kernel\SideNav;

use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Url;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\language\Entity\ContentLanguageSettings;
use Drupal\menu_link_content\Entity\MenuLinkContent;
use Drupal\paatokset_ahjo_api\Entity\Decision;
use Drupal\paatokset_ahjo_api\Entity\Policymaker;
use Drupal\paatokset_ahjo_api\Entity\Organization;
use Drupal\paatokset_ahjo_api\Plugin\Block\PolicymakerSideNav;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\paragraphs\Entity\ParagraphsType;
use Drupal\Tests\paatokset_ahjo_api\Kernel\AhjoEntityKernelTestBase;
use Symfony\Component\HttpFoundation\ParameterBag;
use Prophecy\Argument;

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
    'content_translation',
    'language',
    'link',
    'locale',
    'menu_link_content',
    'menu_block_current_language',
  ];

  /**
   * {@inheritDoc}
   */
  public function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('paragraph');
    $this->installEntitySchema('ahjo_organization');
    $this->installEntitySchema('menu_link_content');

    // Menu links are translatable, like on the site.
    ConfigurableLanguage::createFromLangcode('fi')->save();
    ContentLanguageSettings::loadByEntityTypeBundle('menu_link_content', 'menu_link_content')
      ->setThirdPartySetting('content_translation', 'enabled', TRUE)
      ->save();

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
    $routeMatch->getRouteName()->willReturn(NULL);
    $routeMatch
      ->getParameter(Argument::any())
      ->willReturn(NULL);
    $routeMatch->getParameters()->willReturn(new ParameterBag());
    $routeMatch->getRouteObject()->willReturn(NULL);

    $this->container->set('current_route_match', $routeMatch->reveal());

    $sut = PolicymakerSideNav::create($this->container, [], 'policymaker_sidenav', ['provider' => 'paatokset_ahjo_api']);
    $build = $sut->build();
    $this->assertEmpty($build);

    // Inject policymaker to the current route.
    $policymaker = Policymaker::create([
      'title' => 'Kaupunginvaltuusto',
      'field_organization_type' => 'valtuusto',
      'field_policymaker_id' => 'PM001',
    ]);
    $policymaker->save();

    Organization::create([
      'id' => '123',
      'title' => 'Test parent organization',
      'type' => 1,
      'existing' => 1,
      'organization_above' => NULL,
    ])->save();

    Organization::create([
      'id' => 'PM001',
      'title' => 'Test organization',
      'type' => 1,
      'existing' => 1,
      'organization_above' => '123',
    ])->save();

    $this->assertNotEmpty($policymaker->getOrganization());
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
    $this->assertEquals('/decisionmakers/browse-decision-makers/123', $build['#menu_link_parent']['url']?->toString() ?? '');
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
   * Tests main menu links under the policymaker.
   */
  public function testPolicymakerMenuLinks(): void {
    $routeMatch = $this->prophesize(RouteMatchInterface::class);
    $routeMatch->getRouteName()->willReturn(NULL);
    $routeMatch->getParameters()->willReturn(new ParameterBag());
    $routeMatch->getRouteObject()->willReturn(NULL);
    $routeMatch->getRawParameters()->willReturn(new ParameterBag());
    $routeMatch->getParameter(Argument::any())->willReturn(NULL);
    $this->container->set('current_route_match', $routeMatch->reveal());

    $policymaker = Policymaker::create([
      'title' => 'Kaupunginhallitus',
      'field_organization_type' => 'hallitus',
      'field_policymaker_id' => 'PM002',
    ]);
    $policymaker->save();
    $routeMatch
      ->getParameter('node')
      ->willReturn($policymaker);

    $policymakerLink = MenuLinkContent::create([
      'title' => 'Policymaker',
      'menu_name' => 'main',
      'link' => ['uri' => 'entity:node/' . $policymaker->id()],
      'langcode' => 'en',
    ]);
    $policymakerLink->save();

    $children = [
      // Link in the current language.
      'https://example.com/en' => 'en',
      // Link that is not translated to the current language.
      'https://example.com/fi' => 'fi',
    ];
    foreach ($children as $uri => $langcode) {
      MenuLinkContent::create([
        'title' => $uri,
        'menu_name' => 'main',
        'link' => ['uri' => $uri],
        'parent' => $policymakerLink->getPluginId(),
        'langcode' => $langcode,
      ])->save();
    }

    $sut = PolicymakerSideNav::create($this->container, [], 'policymaker_sidenav', ['provider' => 'paatokset_ahjo_api']);
    $build = $sut->build();
    $this->assertMenuItems([
      // Link to current policymaker.
      $policymaker->toUrl('canonical')->toString(),
      // Link to documents page.
      "/decisionmakers/{$policymaker->id()}/documents",
      // Translated main menu link under the policymaker.
      'https://example.com/en',
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
