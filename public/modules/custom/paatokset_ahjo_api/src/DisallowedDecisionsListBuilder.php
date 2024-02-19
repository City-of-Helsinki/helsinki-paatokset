<?php

declare(strict_types=1);

namespace Drupal\paatokset_ahjo_api;

use Drupal\Core\Config\Entity\DraggableListBuilder;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides a listing of Disallowed Decisions entities.
 */
class DisallowedDecisionsListBuilder extends DraggableListBuilder {

  /**
   * The entity storage class.
   *
   * @var \Drupal\paatokset_ahjo_api\DisallowedDecisionsStorageManager
   */
  protected $storage;

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'disallowed_decisions_overview_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header['sorter'] = '';
    $header['label'] = $this->t('Label');
    $header['id'] = $this->t('Machine name');
    $header['configuration'] = $this->t('Configuration');

    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    $row['sorter'] = ['#markup' => ''];
    $row['label'] = $entity->label();
    $row['id'] = $entity->id();
    $row['configuration'] = $entity->get('configuration');

    return $row + parent::buildRow($entity);
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);

    $form['actions']['submit']['#value'] = $this->t('Save configuration');
    return $form;
  }

}
