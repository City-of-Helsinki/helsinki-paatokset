<?php

namespace Drupal\paatokset_helsinki_kanava\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\node\Entity\Node;

/**
 * Form for Helsinki Kanava settings.
 */
class HelsinkiKanavaForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'paatokset_helsinki_kanava.settings',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'paatokset_helsinki_kanava_settings';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('paatokset_helsinki_kanava.settings');

    $form['city_council_id'] = [
      '#type' => 'textfield',
      '#default_value' => $config->get('city_council_id'),
      '#title' => t('City Council ID'),
      '#description' => 'Id for the city council policymaker.',
    ];

    $form['all_recordings_link'] = [
      '#type' => 'url',
      '#default_value' => $config->get('all_recordings_link'),
      '#title' => t('All recordings link'),
      '#description' => 'Link to listing on helsinkikanava.fi containing all recorded meetings',
    ];

    $form['debug_mode'] = [
      '#type' => 'checkbox',
      '#default_value' => $config->get('debug_mode'),
      '#title' => t('Debug mode for integration.'),
      '#description' => 'Always display livestream for functionality testing, etc.',
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);

    $this->config('paatokset_helsinki_kanava.settings')
      ->set('city_council_id', $form_state->getValue('city_council_id'))
      ->set('all_recordings_link', $form_state->getValue('all_recordings_link'))
      ->set('debug_mode', $form_state->getValue('debug_mode'))
      ->save();
  }

}
