<?php

namespace Drupal\paatokset_ahjo_api\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Settings form for Päätökset default settings.
 *
 * @package Drupal\paatokset_ahjo_api\Form
 */
class DisallowedDecisionsPrefixesForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    // Instantiates this form class.
    $instance = parent::create($container);
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'paatokset_disallowed_decisions_prefixes';
  }

  /**
   * {@inheritdoc}
   */
  public function getEditableConfigNames() {
    return [
      'paatokset_ahjo_api.disallowed_prefixes',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('paatokset_ahjo_api.disallowed_prefixes');

    $form['years'] = [
      '#type' => 'textfield',
      '#default_value' => $config->get('years'),
      '#title' => t('Disallowed decision years'),
      '#description' => t('Years separated by a comma.'),
    ];

    $form['id_prefixes'] = [
      '#type' => 'textfield',
      '#default_value' => $config->get('id_prefixes'),
      '#title' => t('Disallowed decision organisation prefixes'),
      '#description' => t('Org ID prefixes separated by a comma.'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);

    $this->config('paatokset_ahjo_api.disallowed_prefixes')
      ->set('years', $form_state->getValue('years'))
      ->set('id_prefixes', $form_state->getValue('id_prefixes'))
      ->save();
  }

}
