<?php

declare(strict_types=1);

namespace Drupal\paatokset_ahjo_api\Controller;

use Drupal\Core\DependencyInjection\AutowireTrait;
use Drupal\Core\Entity\Controller\EntityViewController;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\node\NodeInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Defines a controller to render a single node.
 */
final class CaseNodeViewController extends EntityViewController {

  use AutowireTrait;

  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    RendererInterface $renderer,
    private readonly RouteMatchInterface $routeMatch,
    private readonly LanguageManagerInterface $languageManager,
  ) {
    parent::__construct($entity_type_manager, $renderer);
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
