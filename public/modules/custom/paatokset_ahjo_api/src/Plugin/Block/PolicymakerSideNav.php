<?php

declare(strict_types=1);

namespace Drupal\paatokset_ahjo_api\Plugin\Block;

use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Menu\MenuLinkManagerInterface;
use Drupal\Core\Menu\MenuLinkTreeInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Routing\RouteProviderInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Template\Attribute;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;
use Drupal\paatokset_ahjo_api\Entity\Policymaker;
use Drupal\paatokset_policymakers\Enum\PolicymakerRoutes;
use Drupal\paatokset_ahjo_api\Service\PolicymakerService;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Routing\Exception\RouteNotFoundException;

/**
 * Provides the policymaker section navigation.
 *
 * Rendered as a sidebar on desktop and as a collapsible section navigation
 * on mobile.
 */
#[Block(
  id: 'policymaker_side_nav',
  admin_label: new TranslatableMarkup('Policymaker side navigation'),
  category: new TranslatableMarkup('Paatokset custom blocks'),
)]
class PolicymakerSideNav extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * The menu where policymaker pages are linked from.
   */
  private const string MENU_NAME = 'main';

  /**
   * Cacheability of the menu tree used in the navigation.
   */
  protected CacheableMetadata $menuCacheability;

  /**
   * Whether the policymaker has been resolved for the current request.
   */
  protected bool $policymakerResolved = FALSE;

  /**
   * {@inheritDoc}
   */
  final public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    private readonly MenuLinkTreeInterface $menuTree,
    private readonly MenuLinkManagerInterface $menuLinkManager,
    private readonly RouteProviderInterface $routeProvider,
    private readonly PolicymakerService $policymakerService,
    #[Autowire(service: 'logger.channel.paatokset_ahjo_api')]
    private readonly LoggerInterface $logger,
    private readonly LanguageManagerInterface $languageManager,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->menuCacheability = new CacheableMetadata();
  }

  /**
   * {@inheritDoc}
   */
  public function build(): array {
    $policymaker = $this->getPolicymaker();

    // Skip block if the policymaker can't be found.
    if (!$policymaker instanceof Policymaker) {
      return [];
    }

    $parentOrgId = $policymaker->getOrganization()?->getParentOrganization()?->getAhjoId();

    $url = Url::fromRoute('paatokset_ahjo_api.browse_policymakers', ['org' => $parentOrgId], [
      'language' => $this->languageManager->getCurrentLanguage(),
    ]);

    $build = [
      '#theme' => 'policymaker_side_navigation',
      '#items' => $this->getItems($policymaker),
      '#menu_attributes' => new Attribute(['class' => 'menu']),
      '#menu_link_parent' => [
        'title' => $this->t('Browse policymakers'),
        'url' => $url,
      ],
      '#attached' => [
        'library' => [
          'hdbt/sidebar-menu-toggle',
        ],
      ],
    ];

    $this->menuCacheability->applyTo($build);

    return $build;
  }

  /**
   * {@inheritDoc}
   */
  public function getCacheTags(): array {
    $cache_tags = [
      'config:system.menu.' . self::MENU_NAME,
    ];

    // Add cache tag for current node, if set.
    $policymaker = $this->getPolicymaker();
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
   * Gets the policymaker for the current request.
   *
   * @return \Drupal\paatokset_ahjo_api\Entity\Policymaker|null
   *   The policymaker, or NULL if the current page is not a policymaker page.
   */
  private function getPolicymaker(): ?Policymaker {
    if (!$this->policymakerResolved) {
      $this->policymakerService->setPolicyMakerByPath();
      $this->policymakerResolved = TRUE;
    }

    $policymaker = $this->policymakerService->getPolicymaker();
    return $policymaker instanceof Policymaker ? $policymaker : NULL;
  }

  /**
   * Generate an array of sidenav links.
   *
   * @param \Drupal\paatokset_ahjo_api\Entity\Policymaker $policymaker
   *   The policymaker.
   *
   * @return array
   *   Array of items:
   *    - title: item title
   *    - url: url of type \Drupal\core\Url
   *    - attributes: element attributes
   */
  private function getItems(Policymaker $policymaker): array {
    $currentPath = Url::fromRoute('<current>')->toString();

    $items = array_merge(
      $this->getDynamicLinks($policymaker),
      $this->getMenuLinks($policymaker),
      $this->getCustomLinks($policymaker),
    );

    foreach ($items as &$item) {
      if ($item['url']->toString() === $currentPath) {
        $item['in_active_trail'] = TRUE;
        $item['is_currentPage'] = TRUE;
      }

      // Apply HDBT attributes to navigation items.
      if (function_exists('_hdbt_menu_item_apply_attributes')) {
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
    assert($policymaker instanceof Policymaker);
    $items = [
      [
        'title' => $policymaker->label(),
        'url' => $this->policymakerService->getLocalizedUrl(),
        'attributes' => new Attribute(),
      ],
    ];

    // Skip other links if policymaker is missing links.
    if (!$orgType = $policymaker->getOrganizationType()) {
      $this->logger->warning('Policymaker organization type is missing for policymaker @name (@id).', [
        '@name' => $policymaker->label(),
        '@id' => $policymaker->id(),
      ]);

      return $items;
    }

    $currentLang = $this->languageManager->getCurrentLanguage()->getId();

    $routes = PolicymakerRoutes::getRoutes($currentLang, $orgType);
    $policymaker_org = $policymaker->getPolicymakerOrganizationFromUrl($currentLang);
    foreach ($routes as $key => $name) {
      try {
        $route = $this->routeProvider->getRouteByName($name);
      }
      catch (RouteNotFoundException) {
        continue;
      }

      $title = match ($key) {
        'documents' => $this->t('Documents'),
        'decisions' => $this->t('Decisions'),
        'discussion_minutes' => $this->t('Discussion minutes'),
        default => call_user_func($route->getDefault('_title_callback'))->render(),
      };

      $items[] = [
        'title' => $title,
        'url' => Url::fromRoute($name, ['organization' => $policymaker_org]),
        'attributes' => new Attribute(),
      ];
    }

    return $items;
  }

  /**
   * Get menu links for policymaker side navigation.
   *
   * The links are the children of the policymaker's link in the main menu.
   *
   * @param \Drupal\node\NodeInterface $policymaker
   *   Policymaker node.
   *
   * @return array
   *   Menu links under current policymaker.
   */
  protected function getMenuLinks(NodeInterface $policymaker): array {
    $links = $this->menuLinkManager->loadLinksByRoute(
      'entity.node.canonical',
      ['node' => $policymaker->id()],
      self::MENU_NAME,
    );

    foreach ($links as $link) {
      $parameters = $this->menuTree->getCurrentRouteMenuTreeParameters(self::MENU_NAME);
      $parameters->setRoot($link->getPluginId())->excludeRoot();
      // Load the whole subtree, like the main menu sidebar block does.
      $parameters->expandedParents = [];

      $tree = $this->menuTree->load(self::MENU_NAME, $parameters);
      if (empty($tree)) {
        continue;
      }

      $tree = $this->menuTree->transform($tree, [
        ['callable' => 'menu.default_tree_manipulators:checkAccess'],
        ['callable' => 'menu.default_tree_manipulators:generateIndexAndSort'],
        // Hide links that are not translated to the current language, like
        // the main menu blocks do.
        [
          'callable' => 'menu_block_current_language_tree_manipulator::filterLanguages',
          'args' => [['menu_link_content']],
        ],
      ]);
      $build = $this->menuTree->build($tree);
      $this->menuCacheability->addCacheableDependency(CacheableMetadata::createFromRenderArray($build));

      if (!empty($build['#items'])) {
        return array_values($build['#items']);
      }
    }

    return [];
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

      $currentLang = $this->languageManager->getCurrentLanguage()->getId();

      if ($entity->hasTranslation($currentLang)) {
        $entity = $entity->getTranslation($currentLang);
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
