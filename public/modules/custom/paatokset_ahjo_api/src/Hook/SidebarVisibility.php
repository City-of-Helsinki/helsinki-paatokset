<?php

declare(strict_types=1);

namespace Drupal\paatokset_ahjo_api\Hook;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Menu\MenuLinkManagerInterface;
use Drupal\Core\Menu\MenuLinkTreeInterface;
use Drupal\Core\Menu\MenuTreeParameters;

/**
 * Sidebar visibility hooks.
 */
readonly class SidebarVisibility {

  public function __construct(
    private MenuLinkManagerInterface $menuLinkManager,
    private MenuLinkTreeInterface $menuTree,
  ) {
  }

  /**
   * Implements hook_sidebar_visibility_alter().
   *
   * The main navigation sidebar is shown for pages that have a parent link
   * in the main menu. On Päätökset, top level pages with child links also
   * show the sidebar.
   *
   * @param array<string, mixed> $variables
   *   Page variables.
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The page entity.
   *
   * @see hdbt_admin_tools_handle_sidebar_visibility()
   */
  #[Hook('sidebar_visibility_alter')]
  public function sidebarVisibilityAlter(array &$variables, ContentEntityInterface $entity): void {
    if (!empty($variables['has_sidebar_first'])) {
      return;
    }

    if (
      $entity->hasField('hide_sidebar_navigation') &&
      $entity->get('hide_sidebar_navigation')->value
    ) {
      return;
    }

    $links = $this->menuLinkManager->loadLinksByRoute(
      "entity.{$entity->getEntityTypeId()}.canonical",
      [$entity->getEntityTypeId() => $entity->id()],
      'main',
    );

    foreach ($links as $link) {
      $parameters = (new MenuTreeParameters())
        ->setRoot($link->getPluginId())
        ->excludeRoot()
        ->setMaxDepth(1)
        ->onlyEnabledLinks();

      if ($this->menuTree->load('main', $parameters)) {
        $variables['has_sidebar_first'] = TRUE;
        return;
      }
    }
  }

}
