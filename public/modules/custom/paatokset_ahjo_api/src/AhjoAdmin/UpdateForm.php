<?php

declare(strict_types=1);

namespace Drupal\paatokset_ahjo_api\AhjoAdmin;

use Drupal\Core\DependencyInjection\AutowireTrait;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\paatokset_ahjo_api\Entity\AhjoUpdatableInterface;
use Drupal\paatokset_ahjo_proxy\AhjoProxy;

/**
 * Allows users to trigger ahjo migrations from Drupal UI.
 */
class UpdateForm extends FormBase {

  use AutowireTrait;

  /**
   * Constructs a new instance.
   */
  public function __construct(
    private readonly AhjoProxy $ahjoProxy,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'ahjo_update_form';
  }

  /**
   * {@inheritDoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $node = $this->getRouteMatch()->getParameter('node');

    if (!$node instanceof AhjoUpdatableInterface) {
      throw new \InvalidArgumentException('Cannot update given entity');
    }

    $form['type'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Type'),
      '#default_value' => $node::getAhjoEndpoint(),
      '#disabled' => TRUE,
    ];

    $form['id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('ID'),
      '#default_value' => $node->getAhjoId(),
      '#disabled' => TRUE,
    ];

    $form['actions']['#type'] = 'actions';
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Update'),
      '#button_type' => 'primary',
    ];

    return $form;
  }

  /**
   * {@inheritDoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $status = $this->ahjoProxy->migrateSingleEntity(
      $form_state->getValue('type'),
      $form_state->getValue('id')
    );

    if ($status === MigrationInterface::RESULT_COMPLETED) {
      $this->messenger()->addStatus($this->t('Entity updated'));
    }
    else {
      $this->messenger()->addError($this->t('Entity update failed with status %status', ['%status' => $status]));
    }
  }

}
