<?php

namespace Drupal\paatokset_ahjo_proxy\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Settings form for the AHJO API entity blacklist.
 *
 * @package Drupal\paatokset_ahjo_proxy\Form
 */
class AhjoProxyBlacklistForm extends ConfigFormBase {

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
    return 'paatokset_ahjo_proxy_blacklist';
  }

  /**
   * {@inheritdoc}
   */
  public function getEditableConfigNames() {
    return [
      'paatokset_ahjo_proxy.blacklist',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('paatokset_ahjo_proxy.blacklist');

    $form['blacklist'] = [
      '#type' => 'textarea',
      '#title' => $this->t('List of disallowed entity IDs, separated by a new line.'),
      '#default_value' => $config->get('ids'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);

    $this->config('paatokset_ahjo_proxy.blacklist')
      ->set('ids', $form_state->getValue('blacklist'))
      ->save();
  }

}
