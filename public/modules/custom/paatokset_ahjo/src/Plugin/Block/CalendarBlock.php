<?php

namespace Drupal\paatokset_ahjo\Plugin\Block;

use Drupal\Core\Block\BlockBase;

/**
 * Provides Calendar Block.
 *
 * @Block(
 *    id = "policymaker_calendar",
 *    admin_label = @Translation("Paatokset policymaker calendar"),
 *    category = @Translation("Paatokset custom blocks")
 * )
 */
class CalendarBlock extends BlockBase {
  /**
   * PolicymakerService instance.
   *
   * @var Drupal\paatokset_ahjo\Service\PolicymakerService
   */
  private $policymakerService;

  /**
   * Class constructor.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->policymakerService = \Drupal::service('Drupal\paatokset_ahjo\Service\PolicymakerService');
  }

  /**
   * Build the attributes.
   */
  public function build() {
    return [
      '#cache' => ['contexts' => ['url.path', 'url.query_args']],
      '#title' => t('Calendar'),
      '#meetings' => ['joku', 'seuraava', 'testitestinen'],
      '#attributes' => [
        'class' => ['policymaker-calendar', 'container'],
      ],
    ];
  }


  /**
   * Builds render arrays of meetings and return them.
   */
  private function getMeetings() {
    $policymaker = $this->policymakerService->getPolicymaker();

    if (!$policymaker) {
      return;
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
   * Get cache contexts.
   */
  public function getCacheContexts() {
    return ['url.path', 'url.query_args'];
  }

}
