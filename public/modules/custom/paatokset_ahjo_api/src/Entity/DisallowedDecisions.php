<?php

declare(strict_types=1);

namespace Drupal\paatokset_ahjo_api\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\Core\Entity\Attribute\ConfigEntityType;
use Drupal\Core\Entity\EntityDeleteForm;
use Drupal\Core\Entity\EntityViewBuilder;
use Drupal\Core\Entity\Routing\AdminHtmlRouteProvider;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\paatokset_ahjo_api\DisallowedDecisionsListBuilder;
use Drupal\paatokset_ahjo_api\DisallowedDecisionsStorageManager;
use Drupal\paatokset_ahjo_api\Form\DisallowedDecisionsForm;

/**
 * Defines the Disallowed Decisions entity.
 */
#[ConfigEntityType(
  id: 'disallowed_decisions',
  label: new TranslatableMarkup('Disallowed Decisions'),
  label_collection: new TranslatableMarkup('Disallowed Decisions'),
  config_prefix: 'disallowed_decisions',
  entity_keys: [
    'id' => 'id',
    'label' => 'label',
    'uuid' => 'uuid',
    'configuration' => 'configuration',
  ],
  handlers: [
    'storage' => DisallowedDecisionsStorageManager::class,
    'view_builder' => EntityViewBuilder::class,
    'list_builder' => DisallowedDecisionsListBuilder::class,
    'form' => [
      'add' => DisallowedDecisionsForm::class,
      'edit' => DisallowedDecisionsForm::class,
      'delete' => EntityDeleteForm::class,
    ],
    'route_provider' => [
      'html' => AdminHtmlRouteProvider::class,
    ],
  ],
  links: [
    'add-form' => '/admin/config/system/disallowed-decisions/add',
    'edit-form' => '/admin/config/system/disallowed-decisions/{disallowed_decisions}/edit',
    'delete-form' => '/admin/config/system/disallowed-decisions/{disallowed_decisions}/delete',
    'collection' => '/admin/config/system/disallowed-decisions',
  ],
  admin_permission: 'administer paatokset',
  config_export: [
    'id',
    'label',
    'configuration',
  ],
)]
class DisallowedDecisions extends ConfigEntityBase {

  /**
   * The Disallowed Decisions ID.
   *
   * @var string
   */
  protected $id;

  /**
   * The Disallowed Decisions label.
   *
   * @var string
   */
  protected $label;

  /**
   * The Disallowed Decisions configuration.
   *
   * @var string
   */
  public $configuration;

}
