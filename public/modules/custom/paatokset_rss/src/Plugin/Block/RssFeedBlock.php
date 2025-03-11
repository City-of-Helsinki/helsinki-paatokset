<?php

declare(strict_types=1);

namespace Drupal\paatokset_rss\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides an RSS feed block.
 */
#[Block(
  id: "rss_feed",
  admin_label: new TranslatableMarkup("RSS Feed"),
)]
class RssFeedBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * The entity type manager service.
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * Constructs a new RssFeedBlock instance.
   */
  public function __construct(
    array $configuration,
    string $plugin_id,
    mixed $plugin_definition,
    EntityTypeManagerInterface $entityTypeManager,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): self {
    return new self(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state): array {
    $form = parent::blockForm($form, $form_state);

    $form['aggregator_feed'] = [
      '#type' => 'entity_autocomplete',
      '#title' => $this->t('RSS feed'),
      '#target_type' => 'aggregator_feed',
      '#selection_handler' => 'default',
      '#description' => $this->t('Select an RSS feed from the aggregator module.'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state): void {
    $this->configuration['aggregator_feed'] = $form_state->getValue('aggregator_feed');
  }

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    $build = [];
    $config = $this->getConfiguration();
    $aggregator_feed_id = $config['aggregator_feed'] ?? NULL;

    if ($aggregator_feed_id) {
      $feed = $this->entityTypeManager->getStorage('aggregator_feed')->load($aggregator_feed_id);

      if ($feed) {
        $build['rss_feed'] = $this->entityTypeManager
          ->getViewBuilder('aggregator_feed')
          ->view($feed, 'default');
      }
    }

    return $build;
  }

}
