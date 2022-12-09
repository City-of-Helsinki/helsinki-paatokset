<?php

namespace Drupal\paatokset_ahjo_proxy\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Settings form for AHJO Proxy.
 *
 * @package Drupal\paatokset_ahjo_proxy\Form
 */
class AhjoProxySettingsForm extends ConfigFormBase {

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
    return 'paatokset_ahjo_proxy_settings';
  }

  /**
   * {@inheritdoc}
   */
  public function getEditableConfigNames() {
    return [
      'paatokset_ahjo_proxy.settings',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('paatokset_ahjo_proxy.settings');

    $form['api_base_url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('API base URL'),
      '#default_value' => $config->get('api_base_url'),
    ];

    $form['api_file_url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('API file URL'),
      '#default_value' => $config->get('api_file_url'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);

    $this->config('paatokset_ahjo_proxy.settings')
      ->set('api_base_url', $form_state->getValue('api_base_url'))
      ->set('api_file_url', $form_state->getValue('api_file_url'))
      ->save();
  }

}
