<?php

declare(strict_types=1);

namespace Drupal\paatokset_rss\Plugin\Block;

use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\views\Views;

/**
 * Provides an RSS feed block.
 */
#[Block(
  id: "rss_feed",
  admin_label: new TranslatableMarkup("RSS Feed"),
)]
class RssFeedBlock extends BlockBase {

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
      $feed = \Drupal::entityTypeManager()->getStorage('aggregator_feed')->load($aggregator_feed_id);

      if ($feed) {
        $build['rss_feed'] = \Drupal::entityTypeManager()
          ->getViewBuilder('aggregator_feed')
          ->view($feed, 'default');
      }
    }

    return $build;
  }

}
