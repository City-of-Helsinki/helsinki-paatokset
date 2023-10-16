<?php

declare(strict_types = 1);

namespace Drupal\paatokset_ahjo_api\Plugin\QueueWorker;

use Drupal\Component\Utility\Unicode;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\Core\Queue\SuspendQueueException;
use Drupal\node\Entity\Node;
use Drupal\node\NodeInterface;
use Drupal\paatokset_ahjo_proxy\AhjoProxy;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Processes cron queue.
 *
 * @QueueWorker(
 *   id = "ahjo_api_org_queue",
 *   title = @Translation("Ahjo Organization chart Queue Worker"),
 * )
 */
class AhjoOrgChartQueueWorker extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  /**
   * The logger.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected LoggerChannelInterface $logger;

  /**
   * Ahjo proxy service.
   *
   * @var \Drupal\paatokset_ahjo_proxy\AhjoProxy
   */
  protected AhjoProxy $ahjoProxy;

  /**
   * Ahjo organization chart queue.
   *
   * @var \Drupal\Core\Queue\QueueInterface
   */
  protected QueueInterface $queue;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    $instance = new static($configuration, $plugin_id, $plugin_definition);
    $instance->ahjoProxy = $container->get('paatokset_ahjo_proxy');
    $instance->logger = $container->get('logger.factory')->get('ahjo_api_org_queue');
    $queue_factory = $container->get('queue');
    $instance->queue = $queue_factory->get('ahjo_api_org_queue');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function processItem($data): void {
    $id = (string) $data['id'];
    $step = (int) $data['step'];
    $max_steps = (int) $data['max_steps'];
    $langcode = $data['langcode'];

    // Only allow finnish and swedish for now.
    $allowed_langs = ['fi', 'sv'];
    if (!in_array($langcode, $allowed_langs)) {
      $langcode = 'fi';
    }

    $this->logger->info('Org: @id (@langcode), step @step out of @max_steps.', [
      '@id' => $id,
      '@langcode' => $langcode,
      '@step' => $step,
      '@max_steps' => $max_steps,
    ]);

    if (!$this->ahjoProxy->isOperational()) {
      $this->logger->error('Ahjo Proxy is not operational, suspending.');
      throw new SuspendQueueException('Ahjo Proxy is not operational, suspending.');
    }

    if (!empty(getenv('AHJO_PROXY_BASE_URL'))) {
      $url = 'organization/single/' . (string) $id . '?apireqlang=' . (string) $langcode;
      $query_string = NULL;
    }
    else {
      $url = 'organization';
      $query_string = 'orgid=' . (string) $id . '&apireqlang=' . (string) $langcode;
    }

    $organization = $this->ahjoProxy->getData($url, $query_string);

    // Local organization is formatted a bit differently.
    if (!empty($organization['decisionMakers'][0]['Organization'])) {
      $organization = $organization['decisionMakers'][0]['Organization'];
    }

    if (empty($organization['ID'])) {
      $this->logger->error('Data not found for @id', [
        '@id' => $id,
      ]);
      return;
    }

    $node = $this->findOrCreateOrg($id, $organization['Name'], $langcode);
    $node->set('field_organization_data', json_encode($organization));

    if (!empty($organization['OrganizationLevelAbove']['organizations'])) {
      $above_org = reset($organization['OrganizationLevelAbove']['organizations']);

      if (!$node->get('field_org_level_above_id')->isEmpty() && $node->get('field_org_level_above_id')->value !== $above_org['ID']) {
        $this->logger->warning('Duplicate ID with different parent: @id, (@parent_id, expecting @existing_parent).', [
          '@id' => $id,
          '@parent_id' => $above_org['ID'],
          '@existing_parent' => $node->get('field_org_level_above_id')->value,
        ]);
      }
      if ($node->isDefaultTranslation()) {
        $node->set('field_org_level_above_id', $above_org['ID']);
      }
    }
    $below_ids = [];
    foreach ($organization['OrganizationLevelBelow']['organizations'] as $org_below) {
      if ($org_below['Existing'] !== 'true') {
        continue;
      }
      $below_ids[] = $org_below['ID'];
    }

    if ($node->isDefaultTranslation()) {
      $node->set('field_org_level_below_ids', $below_ids);
    }

    $node->save();

    if (empty($below_ids)) {
      return;
    }

    // Check if max step amount has been reached yet.
    if ($step >= $max_steps) {
      $this->logger->info('Maximum amount of steps reached.');
      return;
    }

    // Add child items to queue.
    $next_step = $step + 1;
    foreach ($below_ids as $child_id) {
      $data = [
        'id' => $child_id,
        'step' => $next_step,
        'max_steps' => $max_steps,
        'langcode' => $langcode,
      ];

      $item_id = $this->queue->createItem($data);

      if ($item_id) {
        $this->logger->info('Added item to org chart queue: @id (@langcode), (@step out of @max_steps).', [
          '@id' => $child_id,
          '@langcode' => $langcode,
          '@step' => $next_step,
          '@max_steps' => $max_steps,
        ]);
      }
    }
  }

  /**
   * Find or create organization node based on ID.
   *
   * @param string $id
   *   Organization ID.
   * @param string $title
   *   Organization's name.
   * @param string $langcode
   *   Language for org data.
   *
   * @return \Drupal\node\NodeInterface
   *   Loaded or created node, if found.
   */
  private function findOrCreateOrg(string $id, string $title, string $langcode): NodeInterface {
    $query = \Drupal::entityQuery('node')
      ->condition('status', 1)
      ->range(0, 1)
      ->condition('field_policymaker_id', $id)
      ->condition('type', 'organization');

    $ids = $query->execute();
    $found_node = NULL;
    if (!empty($ids)) {
      $found_node = Node::load(reset($ids));
    }

    $title = Unicode::truncate($title, '255', TRUE, TRUE);

    if (!$found_node instanceof NodeInterface) {
      $found_node = Node::create([
        'type' => 'organization',
        'langcode' => $langcode,
        'field_policymaker_id' => $id,
        'title' => $title,
      ]);
    }
    else {
      if ($found_node->hasTranslation($langcode)) {
        $found_node = $found_node->getTranslation($langcode);
      }
      else {
        $found_node = $found_node->addTranslation($langcode, [
          'type' => 'organization',
          'title' => $title,
        ]);
      }
    }

    $found_node->set('title', $title);
    return $found_node;
  }

}
