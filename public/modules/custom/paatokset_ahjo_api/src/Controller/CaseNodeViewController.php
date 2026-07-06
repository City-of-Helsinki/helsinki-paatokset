<?php

declare(strict_types=1);

namespace Drupal\paatokset_ahjo_api\Controller;

use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\node\Controller\NodeViewController;
use Drupal\node\NodeInterface;
use Drupal\paatokset_ahjo_api\Service\CaseService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Defines a controller to render a single node.
 */
final class CaseNodeViewController extends NodeViewController {

  /**
   * Creates a NodeViewController object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer service.
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   The current user.
   * @param \Drupal\Core\Entity\EntityRepositoryInterface $entity_repository
   *   The entity repository.
   * @param \Drupal\Core\Routing\RouteMatchInterface $routeMatch
   *   The route match service.
   * @param \Drupal\Core\Language\LanguageManagerInterface $languageManager
   *   The language manager.
   * @param \Drupal\paatokset_ahjo_api\Service\CaseService $caseService
   *   Case service.
   */
  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    RendererInterface $renderer,
    AccountInterface $current_user,
    EntityRepositoryInterface $entity_repository,
    private readonly RouteMatchInterface $routeMatch,
    private readonly LanguageManagerInterface $languageManager,
    private readonly CaseService $caseService,
  ) {
    parent::__construct($entity_type_manager, $renderer, $current_user, $entity_repository);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('renderer'),
      $container->get('current_user'),
      $container->get('entity.repository'),
      $container->get('current_route_match'),
      $container->get('language_manager'),
      $container->get('paatokset_ahjo_cases')
    );
  }

  /**
   * Return untranslated case (or decision) node on other languages.
   *
   * This method is not called if a translation to the current language exist.
   * Translated cases a have path alias to similar url, and they resolve to
   * `entity.node.canonical` route with a higher priority than this method.
   *
   * @param \Drupal\node\NodeInterface $case
   *   Case node (or decision node).
   */
  public function case(NodeInterface $case): array {
    $this->assertRouteLanguage();

    // @fixme the current url scheme is very complex.
    // This route, ::decision and `entity.node.canonical` for content types case
    // and decision show the same page depending on query parameters. Maybe we
    // could just redirect to decision route here if the query parameter is set
    // in order to reduce complexity?
    //
    // Another idea is to remove path aliases from decision nodes so everything
    // would be handled through this one controller, or try to handle everything
    // with path aliases, so we would not have to have this route for
    // untranslated pages..
    return parent::view($case);
  }

  /**
   * Return untranslated decision node on other languages.
   *
   * Warning: For urls that have a path alias, mainly patterns
   * `fi/asia/[case-id]/[decision-id]` and `sv/arende/[case-id]/[decision-id]`,
   * the route `entity.node.canonical` has a higher priority and is used instead
   * of this controller. However, if the node is not translated to the current
   * language, the path alias does not exist and this route is used instead. No
   * functionality that does not exist in `entity.node.canonical` should be
   * built here so the language versions behave similarly.
   *
   * @param string $case_id
   *   Case diary number.
   * @param \Drupal\node\NodeInterface $decision
   *   Decision native ID.
   *
   * @see \paatokset_ahjo_api_metatags_alter
   *   Fixes metatags.
   */
  public function decision(string $case_id, NodeInterface $decision): array {
    $this->assertRouteLanguage();

    return parent::view($decision);
  }

  /**
   * The _title_callback for untranslated case (or decision) node.
   *
   * @param \Drupal\node\NodeInterface $case
   *   Case node (or decision node).
   */
  public function caseTitle(NodeInterface $case): ?string {
    if (
      $case->bundle() === 'decision' &&
      $case->hasField('field_dm_org_name') &&
      !$case->get('field_dm_org_name')->isEmpty()
    ) {
      return $case->getTitle() . ' - ' . $case->get('field_dm_org_name')->value;
    }

    return $case->getTitle();
  }

  /**
   * The _title_callback for untranslated decision node.
   *
   * @param string $case_id
   *   Case diary number.
   * @param \Drupal\node\NodeInterface $decision
   *   Decision native ID.
   */
  public function decisionTitle(string $case_id, NodeInterface $decision): ?string {
    return $decision->getTitle() . ' - ' . $decision->get('field_dm_org_name')->value;
  }

  /**
   * Validate that the current language matches the selected route.
   *
   * Forbid urls that mix languages, e.g.
   *  - /sv/asia/[case-id]/[decision-id].
   *  - /fi/arende/[case-id]
   */
  private function assertRouteLanguage(): void {
    // Decision route paths should be `paatokset_decision.[langcode]`.
    $currentRoute = $this->routeMatch->getRouteName();
    $currentLanguage = $this->languageManager->getCurrentLanguage()->getId();
    if (!str_ends_with($currentRoute, $currentLanguage)) {
      throw new NotFoundHttpException();
    }
  }

}
