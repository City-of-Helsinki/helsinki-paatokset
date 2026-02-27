<?php

namespace Drupal\paatokset_ahjo_api\AhjoAdmin\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Settings form for Päätökset default settings.
 */
class DisallowedDecisionsPrefixesForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'paatokset_disallowed_decisions_prefixes';
  }

  /**
   * {@inheritdoc}
   */
  public function getEditableConfigNames(): array {
    return [
      'paatokset_ahjo_api.disallowed_prefixes',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form['years'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Disallowed decision years'),
      '#description' => $this->t('Years separated by a comma.'),
      '#config_target' => 'paatokset_ahjo_api.disallowed_prefixes:years',
    ];

    $form['id_prefixes'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Disallowed decision organisation prefixes'),
      '#description' => $this->t('Org ID prefixes separated by a comma.'),
      '#config_target' => 'paatokset_ahjo_api.disallowed_prefixes:id_prefixes',
    ];

    return parent::buildForm($form, $form_state);
  }

}
