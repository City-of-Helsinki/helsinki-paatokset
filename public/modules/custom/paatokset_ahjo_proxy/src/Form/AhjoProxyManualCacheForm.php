<?php

namespace Drupal\paatokset_ahjo_proxy\Form;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Settings form for the AHJO API Open ID connector.
 *
 * @package Drupal\paatokset_ahjo_proxy\Form
 */
class AhjoProxyManualCacheForm extends FormBase {

  /**
   * The cache.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface
   */
  protected CacheBackendInterface $dataCache;

  /**
   * {@inheritdoc}
   */
  public function getCacheMaxAge() : int {
    // Set one day for cache max age.
    return time() + 60 * 60 * 24;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    // Instantiates this form class.
    $instance = parent::create($container);
    $instance->dataCache = $container->get('cache.default');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'paatokset_ahjo_proxy_manual_cache';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['add'] = [
      '#type' => 'details',
      '#title' => $this->t('Add to cache'),
      '#open' => TRUE,
    ];

    $form['add']['url'] = [
      '#type' => 'textfield',
      '#title' => t('URL'),
      '#default_value' => 'https://ahjo.hel.fi:9802/ahjorest/v1/',
    ];

    $form['add']['content'] = [
      '#type' => 'textarea',
      '#title' => t('Content to cache'),
    ];

    $form['delete'] = [
      '#type' => 'details',
      '#title' => $this->t('Delete from cache'),
      '#open' => TRUE,
    ];

    $form['delete']['delete_urls'] = [
      '#type' => 'textarea',
      '#title' => t('Cached URLs to delete, one for each line.'),
    ];

    $form['submit'] = [
      '#type' => 'submit',
      '#default_value' => t('Submit'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $url = $form_state->getValue('url');
    $content = $form_state->getValue('content');
    $delete = $form_state->getValue('delete_urls');

    if (!empty($url) && !empty($content)) {
      $set_key = $this->getCacheKey($url);
      $this->dataCache->set($set_key, $content, $this->getCacheMaxAge(), []);
      $this->messenger()->addMessage(
        $this->t('Added %url to dataCache with ID: %id.', [
          '%url' => $url,
          '%id' => $set_key,
        ]),
      );
    }

    if (empty($delete)) {
      return;
    }
    $delete_urls = explode(PHP_EOL, $delete);
    foreach ($delete_urls as $delete_url) {
      $delete_url = trim($delete_url);
      $delete_key = $this->getCacheKey($delete_url);
      $this->dataCache->delete($delete_key);
      $this->messenger()->addMessage(
        $this->t('Deleted %url from dataCache', [
          '%url' => $delete_url,
        ]),
      );
    }
  }

  /**
   * Gets the cache key for given id.
   *
   * @param string $id
   *   The id.
   *
   * @return string
   *   The cache key.
   */
  protected function getCacheKey(string $id) : string {
    $id = preg_replace('/[^a-z0-9_]+/s', '_', $id);

    return sprintf('ahjo-proxy-%s', $id);
  }

}
