<?php

namespace Drupal\paatokset_ahjo_openid\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Settings form for the AHJO API Open ID connector.
 *
 * @package Drupal\paatokset_ahjo_openid\Form
 */
class SettingsForm extends ConfigFormBase {

  /**
   * State API.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  private $state;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    // Instantiates this form class.
    $instance = parent::create($container);
    $instance->state = $container->get('state');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'paatokset_ahjo_openid_settings';
  }

  /**
   * {@inheritdoc}
   */
  public function getEditableConfigNames() {
    return [
      'paatokset_ahjo_openid.settings',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('paatokset_ahjo_openid.settings');

    $form['auth_url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('URL for authorization flow'),
      '#default_value' => $config->get('auth_url'),
    ];

    $form['token_url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('URL for access and refresh tokens'),
      '#default_value' => $config->get('token_url'),
    ];

    $form['callback_url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Internal callback URL.'),
      '#default_value' => $config->get('callback_url'),
    ];

    $form['client_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Client ID'),
      '#default_value' => $config->get('client_id'),
    ];

    $form['scope'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Scope for authorization request'),
      '#default_value' => $config->get('scope'),
    ];

    $form['cookies'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Cookies for API requests'),
      '#default_value' => $this->state->get('ahjo_api_cookies'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('paatokset_ahjo_openid.settings');

    $settings = [
      'auth_url',
      'token_url',
      'callback_url',
      'client_id',
      'scope',
    ];

    foreach ($settings as $setting) {
      $config->set($setting, $form_state->getValue($setting));
    }

    $this->state->set('ahjo_api_cookies', $form_state->getValue('cookies'));

    $config->save();

    parent::submitForm($form, $form_state);
  }

}
