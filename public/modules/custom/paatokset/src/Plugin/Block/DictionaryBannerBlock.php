<?php

declare(strict_types=1);

namespace Drupal\paatokset\Plugin\Block;

use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;

/**
 * Provides decision-making dictionary banner.
 */
#[Block(
  id: 'dictionary_banner',
  admin_label: new TranslatableMarkup('Paatokset decisions dictionary banner'),
  category: new TranslatableMarkup('Paatokset custom blocks'),
)]
final class DictionaryBannerBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'dictionary_node_id' => NULL,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state): array {
    $form['dictionary_node_id'] = [
      '#type' => 'number',
      '#title' => $this->t('Dictionary page node ID'),
      '#min' => 1,
      '#required' => TRUE,
      '#default_value' => $this->configuration['dictionary_node_id'],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state): void {
    $this->configuration['dictionary_node_id'] = (int) $form_state->getValue('dictionary_node_id');
  }

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    if (!$nid = $this->configuration['dictionary_node_id']) {
      return [];
    }

    return [
      '#theme' => 'paatokset_dictionary_banner',
      '#dictionary_url' => Url::fromRoute('entity.node.canonical', ['node' => $nid]),
    ];
  }

}
