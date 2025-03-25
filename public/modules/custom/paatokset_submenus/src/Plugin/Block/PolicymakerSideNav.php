<?php

declare(strict_types=1);

namespace Drupal\paatokset_submenus\Plugin\Block;

use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Menu\MenuLinkTreeInterface;
use Drupal\Core\Menu\MenuTreeParameters;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Routing\RouteProviderInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Template\Attribute;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;
use Drupal\paatokset_ahjo_api\Entity\Policymaker;
use Drupal\paatokset_policymakers\Enum\PolicymakerRoutes;
use Drupal\paatokset_policymakers\Service\PolicymakerService;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides Agendas Submenu Block.
 */
#[Block(
  id: 'policymaker_side_nav',
  admin_label: new TranslatableMarkup('Policymaker side navigation'),
  category: new TranslatableMarkup('Paatokset custom blocks')
)]
class PolicymakerSideNav extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * Sidenav links.
   *
   * @var ?array
   */
  protected ?array $items;

  /**
   * {@inheritDoc}
   */
  final public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    private readonly MenuLinkTreeInterface $menuTree,
    private readonly RouteProviderInterface $routeProvider,
    private readonly PolicymakerService $policymakerService,
    private readonly string $currentLang,
    private readonly string $currentPath,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->policymakerService->setPolicyMakerByPath();
    $this->items = $this->getItems();
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('menu.link_tree'),
      $container->get('router.route_provider'),
      $container->get('paatokset_policymakers'),
      $container->get('language_manager')->getCurrentLanguage()->getId(),
      $container->get('path.current')->getPath()
    );
  }

  /**
   * {@inheritDoc}
   */
  public function build(): array {
    return [
      '#items' => $this->items,
      '#currentPath' => $this->currentPath,
    ];
  }

  /**
   * {@inheritDoc}
   */
  public function getCacheTags(): array {
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
   * {@inheritDoc}
   */
  public function getCacheContexts(): array {
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
  private function getItems(): ?array {
    $currentPath = Url::fromRoute('<current>')->toString();
    $items = [];

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
        _hdbt_menu_item_apply_attributes($item, TRUE);
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
    assert($policymaker instanceof Policymaker);
    $policymaker_org = $policymaker->getPolicymakerOrganizationFromUrl($this->currentLang);

    $items[] = [
      'title' => $policymaker->get('title')->value,
      'url' => $this->policymakerService->getLocalizedUrl(),
      'attributes' => new Attribute(),
    ];

    $org_type = $policymaker->get('field_organization_type')->value;
    if (in_array($org_type, PolicymakerService::TRUSTEE_TYPES)) {
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

      $route = $this->routeProvider->getRouteByName($localizedRoute);

      if ($key === 'documents') {
        $title = $this->t('Documents');
      }
      elseif ($key === 'decisions') {
        $title = $this->t('Decisions');
      }
      elseif ($key === 'discussion_minutes') {
        $title = $this->t('Discussion minutes');
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
    $localizedDmRoute = 'policymakers.' . $this->currentLang;
    if (!$this->policymakerService->routeExists($localizedDmRoute)) {
      return [];
    }

    $parameters = new MenuTreeParameters();
    $main_menu_top_level = $this->menuTree->load('main', $parameters);
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
      ['callable' => 'menu.default_tree_manipulators:checkAccess'],
      ['callable' => 'menu.default_tree_manipulators:generateIndexAndSort'],
    ];
    $subtree = $this->menuTree->transform($subtree, $manipulators);
    $build = $this->menuTree->build($subtree);

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
    $customLinks = $policymaker->get('field_custom_menu_links')->referencedEntities();
    foreach ($customLinks as $link) {
      assert($link instanceof FieldableEntityInterface);
      if (empty($link->field_referenced_content->entity)) {
        continue;
      }

      $entity = $link->field_referenced_content->entity;

      if ($entity->hasTranslation($this->currentLang)) {
        $entity = $entity->getTranslation($this->currentLang);
      }

      $items[] = [
        'title' => $link->get('field_link_label')->value,
        'url' => $entity->toUrl(),
        'attributes' => new Attribute(),
      ];
    }
    return $items;
  }

}
