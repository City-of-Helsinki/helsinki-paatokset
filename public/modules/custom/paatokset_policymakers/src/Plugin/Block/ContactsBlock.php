<?php

namespace Drupal\paatokset_policymakers\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\node\NodeInterface;
use Drupal\paatokset_policymakers\Service\PolicymakerService;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides Contacts Block.
 *
 * @Block(
 *    id = "policymaker_contacts",
 *    admin_label = @Translation("Paatokset policymaker contacts"),
 *    category = @Translation("Paatokset custom blocks")
 * )
 */
class ContactsBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * {@inheritDoc}
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    private EntityTypeManagerInterface $entityTypeManager,
    private PolicymakerService $policymakerService,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->policymakerService->setPolicyMakerByPath();
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('paatokset_policymakers')
    );
  }

  /**
   * Build the attributes.
   */
  public function build() {
    $contacts = $this->getContacts();

    if (!$contacts || count($contacts) === 0) {
      return;
    }

    $policymaker = $this->policymakerService->getPolicymaker();
    if (!$policymaker instanceof NodeInterface) {
      return;
    }

    return [
      '#cache' => [
        'tags' => [
          'node:' . $policymaker->id(),
          'tpr_unit_list',
        ],
        'contexts' => [
          'url.path',
          'url.query_args',
        ],
      ],
      '#title' => $this->t('Contact information'),
      'contacts' => $this->getContacts(),
      '#attributes' => [
        'class' => ['policymaker-contacts'],
      ],
    ];
  }

  /**
   * Builds render arrays of contacts and return them.
   *
   * @return array|null
   *   Render array, if policymakernode is found.
   */
  private function getContacts(): ?array {
    $policymaker = $this->policymakerService->getPolicymaker();

    if (!$policymaker instanceof NodeInterface || !$policymaker->hasField('field_contacts')) {
      return NULL;
    }

    $renderableEntities = [];

    /** @var \Drupal\Core\Field\EntityReferenceFieldItemList $field */
    $field = $policymaker->get('field_contacts');
    foreach ($field->referencedEntities() as $entity) {
      $view_builder = $this->entityTypeManager->getViewBuilder('tpr_unit');
      $build = $view_builder->view($entity, 'contact_card');

      $renderableEntities[] = $build;
    }

    return $renderableEntities;
  }

  /**
   * Get cache tags.
   */
  public function getCacheTags() {
    return ['tpr_unit_list'];
  }

  /**
   * Get cache contexts.
   */
  public function getCacheContexts() {
    return ['url.path', 'url.query_args'];
  }

}
