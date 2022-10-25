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

    $form['links'] = [
      '#type' => 'details',
      '#title' => $this->t('Links and URLs'),
      '#open' => TRUE,
    ];

    $form['links']['committees_boards_url'] = [
      '#type' => 'textfield',
      '#default_value' => $config->get('committees_boards_url'),
      '#title' => t('Committees and boards URL'),
      '#description' => t('Used on the decision tree page.'),
    ];

    $form['links']['office_holders_url'] = [
      '#type' => 'textfield',
      '#default_value' => $config->get('office_holders_url'),
      '#title' => t('Office holders URL'),
      '#description' => t('Used on the decision tree page.'),
    ];

    $form['alerts'] = [
      '#type' => 'details',
      '#title' => $this->t('Messages and alerts'),
      '#open' => TRUE,
    ];

    $form['alerts']['hidden_decisions_text'] = [
      '#type' => 'text_format',
      '#title' => $this->t('Hidden decisions text'),
      '#format' => $config->get('hidden_decisions_text.format'),
      '#default_value' => $config->get('hidden_decisions_text.value'),
    ];

    $form['alerts']['non_public_attachments_text'] = [
      '#type' => 'text_format',
      '#title' => $this->t('Non public decision attachments text'),
      '#format' => $config->get('non_public_attachments_text.format'),
      '#default_value' => $config->get('non_public_attachments_text.value'),
    ];

    $form['defaults'] = [
      '#type' => 'details',
      '#title' => $this->t('Default fields'),
      '#open' => TRUE,
    ];

    $form['defaults']['calendar_notice_text'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Frontpage calendar notice text'),
      '#default_value' => $config->get('calendar_notice_text'),
    ];

    $form['defaults']['documents_description'] = [
      '#type' => 'text_format',
      '#title' => $this->t('Documents description'),
      '#format' => $config->get('documents_description.format'),
      '#default_value' => $config->get('documents_description.value'),
    ];

    $form['defaults']['meetings_description'] = [
      '#type' => 'text_format',
      '#title' => $this->t('Meetings description'),
      '#format' => $config->get('meetings_description.format'),
      '#default_value' => $config->get('meetings_description.value'),
    ];

    $form['defaults']['recording_description'] = [
      '#type' => 'text_format',
      '#title' => $this->t('Recording description'),
      '#format' => $config->get('recording_description.format'),
      '#default_value' => $config->get('recording_description.value'),
    ];

    $form['defaults']['decisions_description'] = [
      '#type' => 'text_format',
      '#title' => $this->t('Decisions description'),
      '#format' => $config->get('decisions_description.format'),
      '#default_value' => $config->get('decisions_description.value'),
    ];

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
      '#type' => 'textfield',
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
      ->set('calendar_notice_text', $form_state->getValue('calendar_notice_text'))
      ->set('committees_boards_url', $form_state->getValue('committees_boards_url'))
      ->set('office_holders_url', $form_state->getValue('office_holders_url'))
      ->set('hidden_decisions_text.value', $form_state->getValue('hidden_decisions_text')['value'])
      ->set('hidden_decisions_text.format', $form_state->getValue('hidden_decisions_text')['format'])
      ->set('non_public_attachments_text.value', $form_state->getValue('non_public_attachments_text')['value'])
      ->set('non_public_attachments_text.format', $form_state->getValue('non_public_attachments_text')['format'])
      ->set('documents_description.value', $form_state->getValue('documents_description')['value'])
      ->set('documents_description.format', $form_state->getValue('documents_description')['format'])
      ->set('meetings_description.value', $form_state->getValue('meetings_description')['value'])
      ->set('meetings_description.format', $form_state->getValue('meetings_description')['format'])
      ->set('recording_description.value', $form_state->getValue('recording_description')['value'])
      ->set('recording_description.format', $form_state->getValue('recording_description')['format'])
      ->set('decisions_description.value', $form_state->getValue('decisions_description')['value'])
      ->set('decisions_description.format', $form_state->getValue('decisions_description')['format'])
      ->set('banner_heading', $form_state->getValue('banner_heading'))
      ->set('banner_text.value', $form_state->getValue('banner_text')['value'])
      ->set('banner_text.format', $form_state->getValue('banner_text')['format'])
      ->set('banner_label', $form_state->getValue('banner_label'))
      ->set('banner_url', $form_state->getValue('banner_url'))
      ->save();
  }

}
