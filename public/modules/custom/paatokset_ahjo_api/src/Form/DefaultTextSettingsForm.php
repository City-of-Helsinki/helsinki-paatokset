<?php

namespace Drupal\paatokset_ahjo_api\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Settings form for the AHJO API Open ID connector.
 *
 * @package Drupal\paatokset_ahjo_openid\Form
 */
class DefaultTextSettingsForm extends ConfigFormBase {

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
    return 'paatokset_default_text_settings';
  }

  /**
   * {@inheritdoc}
   */
  public function getEditableConfigNames() {
    return [
      'paatokset_ahjo_api.default_texts',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('paatokset_ahjo_api.default_texts');

    $form['banner'] = [
      '#type' => 'details',
      '#title' => $this->t('Decision banner'),
      '#open' => TRUE,
    ];

    $form['banner']['banner_heading'] = [
      '#type' => 'textfield',
      '#default_value' => $config->get('banner_heading'),
      '#title' => t('Banner heading'),
    ];

    $form['banner']['banner_text'] = [
      '#type' => 'text_format',
      '#title' => $this->t('Banner content'),
      '#format' => $config->get('banner_text.format'),
      '#default_value' => $config->get('banner_text.value'),
    ];

    $form['banner']['banner_label'] = [
      '#type' => 'textfield',
      '#default_value' => $config->get('banner_label'),
      '#title' => t('CTA button label'),
    ];

    $form['banner']['banner_url'] = [
      '#type' => 'url',
      '#default_value' => $config->get('banner_url'),
      '#title' => t('CTA button link'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);

    $this->config('paatokset_ahjo_api.default_texts')
      ->set('banner_heading', $form_state->getValue('banner_heading'))
      ->set('banner_text.value', $form_state->getValue('banner_text')['value'])
      ->set('banner_text.format', $form_state->getValue('banner_text')['format'])
      ->set('banner_label', $form_state->getValue('banner_label'))
      ->set('banner_url', $form_state->getValue('banner_url'))
      ->save();
  }

}
