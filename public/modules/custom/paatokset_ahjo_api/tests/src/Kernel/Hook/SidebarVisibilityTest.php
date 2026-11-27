<?php

declare(strict_types=1);

namespace Drupal\Tests\paatokset_ahjo_api\Kernel\Hook;

use Drupal\Core\Menu\MenuLinkManagerInterface;
use Drupal\Core\Menu\MenuLinkTreeInterface;
use Drupal\menu_link_content\Entity\MenuLinkContent;
use Drupal\node\Entity\Node;
use Drupal\paatokset_ahjo_api\Hook\SidebarVisibility;
use Drupal\Tests\paatokset_ahjo_api\Kernel\AhjoEntityKernelTestBase;

/**
 * Tests sidebar visibility hooks.
 *
 * @see \Drupal\paatokset_ahjo_api\Hook\SidebarVisibility
 */
class SidebarVisibilityTest extends AhjoEntityKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'link',
    'menu_link_content',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('menu_link_content');
  }

  /**
   * Tests sidebar visibility for top level pages.
   */
  public function testTopLevelPage(): void {
    $page = Node::create(['type' => 'page', 'title' => 'Page']);
    $page->save();

    $link = MenuLinkContent::create([
      'title' => 'Page',
      'menu_name' => 'main',
      'link' => ['uri' => 'entity:node/' . $page->id()],
    ]);
    $link->save();

    // Top level page without child links.
    $this->assertFalse($this->hasSidebarFirst($page));

    // Disabled child links are ignored.
    $child = MenuLinkContent::create([
      'title' => 'Child',
      'menu_name' => 'main',
      'link' => ['uri' => 'https://example.com'],
      'parent' => $link->getPluginId(),
      'enabled' => FALSE,
    ]);
    $child->save();
    $this->assertFalse($this->hasSidebarFirst($page));

    // Top level page with child links.
    $child->set('enabled', TRUE)->save();
    $this->assertTrue($this->hasSidebarFirst($page));

    // The hook does not hide sidebars enabled elsewhere.
    $other = Node::create(['type' => 'page', 'title' => 'Other']);
    $other->save();
    $this->assertTrue($this->hasSidebarFirst($other, TRUE));
  }

  /**
   * Runs the hook and returns the resulting has_sidebar_first value.
   */
  private function hasSidebarFirst(Node $entity, bool $hasSidebarFirst = FALSE): bool {
    $variables = ['has_sidebar_first' => $hasSidebarFirst];

    $sut = new SidebarVisibility(
      $this->container->get(MenuLinkManagerInterface::class),
      $this->container->get(MenuLinkTreeInterface::class),
    );
    $sut->sidebarVisibilityAlter($variables, $entity);

    return $variables['has_sidebar_first'];
  }

}
