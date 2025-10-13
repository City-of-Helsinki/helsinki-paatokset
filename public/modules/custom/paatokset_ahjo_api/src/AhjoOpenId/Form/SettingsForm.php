<?php

declare(strict_types=1);

namespace Drupal\paatokset_ahjo_api\AhjoOpenId\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Settings form for the AHJO API Open ID connector.
 *
 * @todo Do these need a separate form?
 */
final class SettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'paatokset_ahjo_openid_settings';
  }

  /**
   * {@inheritdoc}
   */
  public function getEditableConfigNames(): array {
    return [
      'paatokset_ahjo_api.settings',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form['auth_url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('URL for authorization flow'),
      '#config_target' => 'paatokset_ahjo_api.settings:openid_settings.auth_url',
    ];

    $form['token_url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('URL for access and refresh tokens'),
      '#config_target' => 'paatokset_ahjo_api.settings:openid_settings.token_url',
    ];

    $form['client_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Client ID'),
      '#config_target' => 'paatokset_ahjo_api.settings:openid_settings.client_id',
    ];

    $form['scope'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Scope for authorization request'),
      '#config_target' => 'paatokset_ahjo_api.settings:openid_settings.scope',
    ];

    return parent::buildForm($form, $form_state);
  }

}
