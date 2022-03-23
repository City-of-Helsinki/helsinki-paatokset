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
   * Set cache age to zero.
   */
  public function getCacheMaxAge() {
    // If you need to redefine the Max Age for that block.
    return 0;
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

    $policymaker = $this->policymakerService->getPolicymaker();

    if (!$policymaker instanceof NodeInterface || $policymaker->getType() !== 'policymaker') {
      return $items;
    }

    $items[] = [
      'title' => $policymaker->get('field_ahjo_title')->value,
      'url' => $this->policymakerService->getLocalizedUrl(),
      'attributes' => new Attribute(),
    ];

    $policymaker_url = $policymaker->toUrl()->toString();
    $policymaker_url_bits = explode('/', $policymaker_url);
    $policymaker_org = array_pop($policymaker_url_bits);
    $menu_tree = \Drupal::menuTree();
    $routeProvider = \Drupal::service('router.route_provider');
    $dmMenuLink = NULL;
    $localizedDmRoute = 'policymakers.' . $this->currentLang;

    if ($this->policymakerService->routeExists($localizedDmRoute)) {
      $parameters = new MenuTreeParameters();
      $main_menu_top_level = $menu_tree->load('main', $parameters);
      $dmUrl = Url::fromRoute($localizedDmRoute)->toString();
      $dmParentLink = NULL;
      foreach ($main_menu_top_level as $menuLink) {
        $dmParentLink = NULL;
        $linkUrl = $menuLink->link->getUrlObject();

        if ($linkUrl && $linkUrl->toString() === $dmUrl) {
          $dmParentLink = $menuLink;
          break;
        }
      }

      if ($dmParentLink) {
        foreach ($dmParentLink->subtree as $subMenuLink) {
          $linkUrl = $subMenuLink->link->getUrlObject();

          if ($linkUrl && $linkUrl->toString() === $policymaker_url) {
            $dmMenuLink = $subMenuLink;
            break;
          }
        }
      }
    }

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

    foreach ($routes as $key => $route) {
      if ($key === 'discussion_minutes' && $org_type !== 'Valtuusto') {
        continue;
      }

      $localizedRoute = "$route.$this->currentLang";
      if ($this->policymakerService->routeExists($localizedRoute)) {
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
    }

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

    foreach ($items as $key => $item) {
      if ($item['url']->toString() === $currentPath) {
        $items[$key]['in_active_trail'] = TRUE;
        $items[$key]['is_currentPage'] = TRUE;
      }
    }

    if ($dmMenuLink && $dmMenuLink->subtree) {
      $items = array_merge($items, $menu_tree->build($dmMenuLink->subtree)['#items']);
    }

    // Apply HDBT attributes to navigation items.
    if (function_exists('_hdbt_menu_item_apply_attributes')) {
      foreach ($items as &$item) {
        _hdbt_menu_item_apply_attributes($item);
      }
    }

    return $items;
  }

}
