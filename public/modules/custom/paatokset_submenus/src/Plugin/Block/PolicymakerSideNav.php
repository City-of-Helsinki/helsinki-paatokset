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
    $options = $this->itemsToOptions();

    return [
      '#cache' => ['contexts' => ['url.path', 'url.query_args']],
      '#title' => 'Viranhaltijapäätökset',
      '#items' => [[
        'title' => t('Policymakers'),
        'url' => Url::fromRoute('policymakers.fi')->setOption('attributes', ['icon' => 'angle-left']),
        'below' => $this->items,
      ],
      ],
      '#currentPath' => \Drupal::service('path.current')->getPath(),
      '#options' => $options['options'],
      '#current_option' => $options['current_option'],
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
      'title' => $policymaker->getTitle(),
      'url' => $policymaker->toUrl(),
      'attributes' => new Attribute(),
    ];

    $routeProvider = \Drupal::service('router.route_provider');
    $routes = $policymaker->get('field_organization_type')->value === 'trustee' ?
      PolicymakerRoutes::getTrusteeRoutes() :
      PolicymakerRoutes::getOrganizationRoutes();

    foreach ($routes as $route) {
      $localizedRoute = "$route.$currentLanguage";
      if ($this->policymakerService->routeExists($localizedRoute)) {
        $route = $routeProvider->getRouteByName($localizedRoute);
        $title = call_user_func($route->getDefault('_title_callback'))->render();
        $items[] = [
          'title' => $title,
          'url' => Url::fromRoute($localizedRoute, ['organization' => strtolower($policymaker->get('title')->value)]),
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
      }
    }

    return $items;
  }

  /**
   * Transform items to HDS dropdown options.
   *
   * @return array
   *   Array of options + current option
   */
  private function itemsToOptions() {
    if (!is_array($this->items)) {
      return [];
    }

    $currentPath = $currentPath = Url::fromRoute('<current>')->toString();
    $currentOption = NULL;
    $options = [];
    foreach ($this->items as $option) {
      $option = [
        'label' => $option['title'],
        'value' => $option['url']->toString(),
      ];

      if ($option['value'] === $currentPath) {
        $currentOption = $option;
      }

      $options[] = $option;
    }

    return [
      'options' => json_encode($options),
      'current_option' => json_encode($currentOption),
    ];
  }

}
