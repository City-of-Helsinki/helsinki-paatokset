<?php

namespace Drupal\paatokset_submenus\Plugin\Block;

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
   * Class constructor.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->policymakerService = \Drupal::service('paatokset_policymakers');
    $this->policymakerService->setPolicyMakerByPath();
    $this->items = $this->getItems();
  }

  /**
   * Build the attributes.
   */
  public function build() {
    return [
      '#items' => [[
        'title' => t('Policymakers'),
        'url' => Url::fromRoute('policymakers.fi')->setOption('attributes', ['icon' => 'angle-left']),
        'below' => $this->items,
      ],
      ],
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
    $currentLanguage = \Drupal::languageManager()->getCurrentLanguage()->getId();
    $currentPath = Url::fromRoute('<current>')->toString();
    $items = [];

    $policymaker = $this->policymakerService->getPolicymaker();

    if (!$policymaker instanceof NodeInterface || $policymaker->getType() !== 'policymaker') {
      return $items;
    }

    $items[] = [
      'title' => $policymaker->get('field_ahjo_title')->value,
      'url' => $policymaker->toUrl(),
      'attributes' => new Attribute(),
    ];

    $policymaker_url = $policymaker->toUrl()->toString();
    $policymaker_url_bits = explode('/', $policymaker_url);
    $policymaker_org = array_pop($policymaker_url_bits);

    $routeProvider = \Drupal::service('router.route_provider');
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

      $localizedRoute = "$route.$currentLanguage";
      if ($this->policymakerService->routeExists($localizedRoute)) {
        $route = $routeProvider->getRouteByName($localizedRoute);
        $title = call_user_func($route->getDefault('_title_callback'))->render();
        $items[] = [
          'title' => $title,
          'url' => Url::fromRoute($localizedRoute, ['organization' => $policymaker_org]),
          'attributes' => new Attribute(),
        ];
      }
    }

    $customLinks = $policymaker->field_custom_menu_links->referencedEntities();
    foreach ($customLinks as $link) {
      $items[] = [
        'title' => $link->get('field_link_label')->value,
        'url' => $link->field_referenced_content->entity->toUrl(),
        'attributes' => new Attribute(),
      ];
    }

    foreach ($items as $key => $item) {
      if ($item['url']->toString() === $currentPath) {
        $items[$key]['is_active'] = TRUE;
        $items[$key]['attributes']->setAttribute('aria-current', 'page');
      }
    }

    return $items;
  }

}
