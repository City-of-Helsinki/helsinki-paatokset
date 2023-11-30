<?php

namespace Drupal\paatokset_policymakers\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\node\NodeInterface;

/**
 * Provides Contacts Block.
 *
 * @Block(
 *    id = "policymaker_contacts",
 *    admin_label = @Translation("Paatokset policymaker contacts"),
 *    category = @Translation("Paatokset custom blocks")
 * )
 */
class ContactsBlock extends BlockBase {

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
    $entities = $policymaker->get('field_contacts')->referencedEntities();
    foreach ($entities as $entity) {
      $view_builder = \Drupal::entityTypeManager()->getViewBuilder('tpr_unit');
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
