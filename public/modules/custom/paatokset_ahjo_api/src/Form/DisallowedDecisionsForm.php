<?php

declare(strict_types = 1);

namespace Drupal\paatokset_ahjo_api\Form;

use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Disallowed Decisions entity form.
 */
class DisallowedDecisionsForm extends EntityForm {

  /**
   * The Disallowed Decisions Storage Manager.
   *
   * @var \Drupal\paatokset_ahjo_api\DisallowedDecisionsStorageManager
   */
  protected $entityStorageManager;

  /**
   * Constructs a DisallowedDecisionsForm object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entityTypeManager.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager) {
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);

    $disallowed_decisions = $this->entity;
    $form['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Label'),
      '#maxlength' => 255,
      '#default_value' => $disallowed_decisions->label(),
      '#description' => $this->t("Organization ID for disallowed decisions."),
      '#required' => TRUE,
    ];

    $form['id'] = [
      '#type' => 'machine_name',
      '#default_value' => $disallowed_decisions->id(),
      '#machine_name' => [
        'exists' => '\Drupal\paatokset_ahjo_api\Entity\DisallowedDecisions::load',
      ],
      '#changeable_state' => !$disallowed_decisions->isNew(),
    ];

    $form['configuration'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Configuration'),
      '#default_value' => $disallowed_decisions->get('configuration'),
      '#description' => $this->t("Configuration of disallowed decisions. Year followed by section numbers. Years separated by ---"),
      '#required' => TRUE,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $disallowed_decisions = $this->entity;
    $status = $disallowed_decisions->save();
    $status = 0;

    switch ($status) {
      case SAVED_NEW:
        $this->messenger()
          ->addMessage($this->t('Added disallowed decisions for: %label.', [
            '%label' => $disallowed_decisions->label(),
          ]));
        break;

      default:
        $this->messenger()
          ->addMessage($this->t('Saved disallowed decisions for %label.', [
            '%label' => $disallowed_decisions->label(),
          ]));
    }
    $form_state->setRedirectUrl($disallowed_decisions->toUrl('collection'));
  }

}
