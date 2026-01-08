<?php

declare(strict_types=1);

namespace Drupal\paatokset_ahjo_api\Form;

use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Form\FormStateInterface;

/**
 * Disallowed Decisions entity form.
 */
class DisallowedDecisionsForm extends EntityForm {

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) : array {
    $form = parent::form($form, $form_state);

    $form['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Label'),
      '#maxlength' => 255,
      '#default_value' => $this->entity->label(),
      '#description' => $this->t("Organization ID for disallowed decisions."),
      '#required' => TRUE,
    ];

    $form['id'] = [
      '#type' => 'machine_name',
      '#default_value' => $this->entity->id(),
      '#machine_name' => [
        'exists' => '\Drupal\paatokset_ahjo_api\Entity\DisallowedDecisions::load',
      ],
      '#changeable_state' => !$this->entity->isNew(),
    ];

    $form['configuration'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Configuration'),
      '#default_value' => $this->entity->get('configuration'),
      '#description' => $this->t("Configuration of disallowed decisions. Year followed by section numbers. Years separated by ---"),
      '#required' => TRUE,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $result = parent::save($form, $form_state);
    $message = ($result === SAVED_NEW)
      ? $this->t('Added disallowed decisions for: %label.', [
        '%label' => $this->entity->label(),
      ])
      : $this->t('Saved disallowed decisions for %label.', [
        '%label' => $this->entity->label(),
      ]);

    $this->messenger()->addStatus($message);
    $form_state->setRedirectUrl($this->entity->toUrl('collection'));
    return $result;
  }

}
