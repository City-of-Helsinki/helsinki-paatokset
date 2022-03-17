<?php

namespace Drupal\paatokset_policymakers\Controller;

use Drupal\node\NodeInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\node\Controller\NodeViewController;
use Drupal\paatokset_policymakers\Service\PolicymakerService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Defines a controller to render a single node.
 */
class PolicymakerNodeViewController extends NodeViewController {

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * The entity repository service.
   *
   * @var \Drupal\Core\Entity\EntityRepositoryInterface
   */
  protected $entityRepository;

  /**
   * Policymaker service.
   *
   * @var \Drupal\paatokset_policymakers\Service\PolicymakerService
   */
  private $policymakerService;

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
   * @param \Drupal\paatokset_policymakers\Service\PolicymakerService $policymaker_service
   *   Policymaker service.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, RendererInterface $renderer, AccountInterface $current_user, EntityRepositoryInterface $entity_repository, PolicymakerService $policymaker_service) {
    parent::__construct($entity_type_manager, $renderer, $current_user, $entity_repository);
    $this->policymakerService = $policymaker_service;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('renderer'),
      $container->get('current_user'),
      $container->get('entity.repository'),
      $container->get('paatokset_policymakers')
    );
  }

  /**
   * Return untranslated policymaker node on other languages.
   */
  public function policymaker(string $organization) {
    $this->policymakerService->setPolicyMakerByPath();
    $node = $this->policymakerService->getPolicyMaker();

    if (!$node instanceof EntityInterface) {
      $node = $this->policymakerService->getTrusteeById($organization);
    }

    if ($node instanceof EntityInterface) {
      return parent::view($node, 'full');
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
    if ($policymaker instanceof NodeInterface) {
      return $policymaker->title->value;
    }
    return ucfirst($organization);
  }

}
