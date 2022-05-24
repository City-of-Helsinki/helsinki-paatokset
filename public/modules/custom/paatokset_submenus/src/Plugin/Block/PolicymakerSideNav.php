<?php

namespace Drupal\paatokset_submenus\Plugin\Block;

use Drupal\Core\Menu\MenuTreeParameters;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Template\Attribute;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;
use Drupal\paatokset_policymakers\Enum\PolicymakerRoutes;

/**
 * Provides Agendas Submenu Block.
 *
 * @Block(
 *    id = "policymaker_side_nav",
 *    admin_label = @Translation("Policymaker side navigation"),
 *    category = @Translation("Paatokset custom blocks")
 * )
 */
class PolicymakerSideNav extends BlockBase {

  /**
   * PolicymakerService instance.
   *
   * @var Drupal\paatokset_policymakers\Service\PolicymakerService
   */
  private $policymakerService;

  /**
   * Current language ID.
   *
   * @var string
   */
  private $currentLang;

  /**
   * Class constructor.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->policymakerService = \Drupal::service('paatokset_policymakers');
    $this->policymakerService->setPolicyMakerByPath();
    $this->currentLang = \Drupal::languageManager()->getCurrentLanguage()->getId();
    $this->items = $this->getItems();
  }

  /**
   * Build the attributes.
   */
  public function build() {

    $route = 'policymakers.' . $this->currentLang;
    if (!$this->policymakerService->routeExists($route)) {
      $route = 'policymakers.fi';
    }

    return [
      '#items' => $this->items,
      '#currentPath' => \Drupal::service('path.current')->getPath(),
    ];
  }

  /**
   * Get cache tags.
   */
  public function getCacheTags() {
    $cache_tags = [
      'config:system.menu.main',
    ];

    // Add cache tag for current node, if set.
    $policymaker = $this->policymakerService->getPolicymaker();
    if ($policymaker instanceof NodeInterface) {
      $cache_tags[] = 'node:' . $policymaker->id();
    }

    return $cache_tags;
  }

  /**
   * Get cache contexts.
   */
  public function getCacheContexts() {
    return ['url.path', 'url.query_args'];
  }

  /**
   * Generate an array of sidenav links.
   *
   * @return array|null
   *   Array of items:
   *    - title: item title
   *    - url: url of type \Drupal\core\Url
   *    - attributes: element attributes
   */
  private function getItems() {
    $currentPath = Url::fromRoute('<current>')->toString();
    $items = [];
    $dynamic_links = [];
    $menu_links = [];
    $custom_links = [];

    // Return empty result if policymaker can't be found.
    $policymaker = $this->policymakerService->getPolicymaker();
    if (!$policymaker instanceof NodeInterface || $policymaker->getType() !== 'policymaker') {
      return $items;
    }

    $policymaker_url = $policymaker->toUrl()->toString();

    $dynamic_links = $this->getDynamicLinks($policymaker);
    $menu_links = $this->getMenuLinks($policymaker_url);
    $custom_links = $this->getCustomlinks($policymaker);
    $items = array_merge($dynamic_links, $menu_links, $custom_links);

    foreach ($items as $key => $item) {
      if ($item['url']->toString() === $currentPath) {
        $items[$key]['in_active_trail'] = TRUE;
        $items[$key]['is_currentPage'] = TRUE;
      }
    }

    // Apply HDBT attributes to navigation items.
    if (function_exists('_hdbt_menu_item_apply_attributes')) {
      foreach ($items as &$item) {
        _hdbt_menu_item_apply_attributes($item);
      }
    }

    return $items;
  }

  /**
   * Get dynamic links for policymaker.
   *
   * @param \Drupal\node\NodeInterface $policymaker
   *   Policymaker node.
   *
   * @return array
   *   Dynamic links.
   */
  protected function getDynamicLinks(NodeInterface $policymaker): array {
    $items = [];
    $policymaker_url = $policymaker->toUrl()->toString();
    $policymaker_url_bits = explode('/', $policymaker_url);
    $policymaker_org = array_pop($policymaker_url_bits);
    $routeProvider = \Drupal::service('router.route_provider');

    $items[] = [
      'title' => $policymaker->get('field_ahjo_title')->value,
      'url' => $this->policymakerService->getLocalizedUrl(),
      'attributes' => new Attribute(),
    ];

    $trustee_types = [
      'Viranhaltija',
      'Luottamushenkilö',
    ];

    $org_type = $policymaker->get('field_organization_type')->value;
    if (in_array($org_type, $trustee_types)) {
      $routes = PolicymakerRoutes::getTrusteeRoutes();
    }
    else {
      $routes = PolicymakerRoutes::getOrganizationRoutes();
    }

    foreach ($routes as $key => $name) {
      if ($key === 'discussion_minutes' && $org_type !== 'Valtuusto') {
        continue;
      }

      $localizedRoute = "$name.$this->currentLang";

      if (!$this->policymakerService->routeExists($localizedRoute)) {
        continue;
      }

      $route = $routeProvider->getRouteByName($localizedRoute);

      if ($key === 'documents') {
        $title = t('Documents');
      }
      elseif ($key === 'decisions') {
        $title = t('Decisions');
      }
      elseif ($key === 'discussion_minutes') {
        $title = t('Discussion minutes');
      }
      else {
        $title = call_user_func($route->getDefault('_title_callback'))->render();
      }

      $items[] = [
        'title' => $title,
        'url' => Url::fromRoute($localizedRoute, ['organization' => $policymaker_org]),
        'attributes' => new Attribute(),
      ];
    }

    return $items;
  }

  /**
   * Get menu links for policymaker side navigation.
   *
   * @param string $policymaker_url
   *   Policymaker URL.
   *
   * @return array
   *   Menu links under current policymaker.
   */
  protected function getMenuLinks(string $policymaker_url): array {
    $menu_tree = \Drupal::menuTree();
    $localizedDmRoute = 'policymakers.' . $this->currentLang;
    if (!$this->policymakerService->routeExists($localizedDmRoute)) {
      return [];
    }

    $parameters = new MenuTreeParameters();
    $main_menu_top_level = $menu_tree->load('main', $parameters);
    $dmUrl = Url::fromRoute($localizedDmRoute)->toString();

    // Get decisionmakers menu link.
    $dmParentLink = NULL;
    foreach ($main_menu_top_level as $menuLink) {
      $linkUrl = $menuLink->link->getUrlObject();

      if ($linkUrl && $linkUrl->toString() === $dmUrl) {
        $dmParentLink = $menuLink;
        break;
      }
    }

    // Empty result if parent link can't be found.
    if ($dmParentLink === NULL) {
      return [];
    }

    // Try to find current decisionmaker under decisionmakers item.
    $dmMenuLink = NULL;
    foreach ($dmParentLink->subtree as $subMenuLink) {
      $linkUrl = $subMenuLink->link->getUrlObject();

      if ($linkUrl && $linkUrl->toString() === $policymaker_url) {
        $dmMenuLink = $subMenuLink;
        break;
      }
    }

    if ($dmMenuLink === NULL || empty($dmMenuLink->subtree)) {
      return [];
    }

    // Use manipulators to get correct item order.
    $subtree = $dmMenuLink->subtree;
    $manipulators = [
      ['callable' => 'menu.default_tree_manipulators:generateIndexAndSort'],
    ];
    $subtree = $menu_tree->transform($subtree, $manipulators);
    $build = $menu_tree->build($subtree);

    if (!isset($build['#items']) || empty($build['#items'])) {
      return [];
    }
    return $build['#items'];
  }

  /**
   * Get custom links from policymaker.
   *
   * @param \Drupal\node\NodeInterface $policymaker
   *   Policymaker node.
   *
   * @return array
   *   Custom links.
   */
  protected function getCustomLinks(NodeInterface $policymaker): array {
    $items = [];
    $customLinks = $policymaker->field_custom_menu_links->referencedEntities();
    foreach ($customLinks as $link) {
      if (empty($link->field_referenced_content->entity)) {
        continue;
      }

      $items[] = [
        'title' => $link->get('field_link_label')->value,
        'url' => $link->field_referenced_content->entity->toUrl(),
        'attributes' => new Attribute(),
      ];
    }
    return $items;
  }

}
