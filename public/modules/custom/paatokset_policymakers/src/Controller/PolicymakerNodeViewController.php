<?php

declare(strict_types=1);

namespace Drupal\paatokset_policymakers\Controller;

use Drupal\Core\DependencyInjection\AutowireTrait;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\node\Controller\NodeViewController;
use Drupal\node\NodeInterface;
use Drupal\paatokset_ahjo_api\Service\PolicymakerService;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Defines a controller to render a single node.
 */
class PolicymakerNodeViewController extends NodeViewController {

  use AutowireTrait;

  public function __construct(
    EntityTypeManagerInterface $entityTypeManager,
    RendererInterface $renderer,
    AccountInterface $currentUser,
    EntityRepositoryInterface $entityRepository,
    private readonly PolicymakerService $policymakerService,
  ) {
    parent::__construct($entityTypeManager, $renderer, $currentUser, $entityRepository);
  }

  /**
   * Apply custom logic for handling head links.
   *
   * @param array &$page
   *   The page render array.
   */
  protected function handleHeadLinks(array &$page) {
    // Remove canonical URL from the policymaker node that is being rendered to
    // avoid duplicate canonical URLs.
    array_walk($page['#attached']['html_head_link'], function ($item, $key) use (&$page) {
      if ($item[0]['rel'] == 'canonical') {
        unset($page['#attached']['html_head_link'][$key]);
      }
    });
  }

  /**
   * Return untranslated policymaker node on other languages.
   */
  public function policymaker(string $organization) {
    $this->policymakerService->setPolicyMakerByPath();
    $node = $this->policymakerService->getPolicyMaker();

    if (!$node instanceof EntityInterface) {
      $node = $this->policymakerService->getTrusteeByPath($organization);
    }

    if ($node instanceof EntityInterface) {
      $page = parent::view($node, 'full');
      $this->handleHeadLinks($page);
      return $page;
    }

    throw new NotFoundHttpException();
  }

  /**
   * Return title for untranslated policymaker node.
   *
   * @param string $organization
   *   Organization parameter.
   */
  public function policymakerTitle(string $organization) {
    $policymaker = $this->policymakerService->getPolicyMaker();
    if (!$policymaker instanceof EntityInterface) {
      $policymaker = $this->policymakerService->getTrusteeByPath($organization);
    }
    if ($policymaker instanceof NodeInterface) {
      return $policymaker->title->value;
    }
    return ucfirst($organization);
  }

}
