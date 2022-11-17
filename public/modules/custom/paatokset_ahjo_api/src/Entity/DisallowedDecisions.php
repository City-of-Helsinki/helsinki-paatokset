<?php

declare(strict_types = 1);

namespace Drupal\paatokset_ahjo_api\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;

/**
 * Defines the Disallowed Decisions entity.
 *
 * @ConfigEntityType(
 *   id = "disallowed_decisions",
 *   label = @Translation("Disallowed Decisions"),
 *   label_collection = @Translation("Disallowed Decisions"),
 *   handlers = {
 *     "storage" = "Drupal\paatokset_ahjo_api\DisallowedDecisionsStorageManager",
 *     "view_builder" = "Drupal\Core\Entity\EntityViewBuilder",
 *     "list_builder" = "Drupal\paatokset_ahjo_api\DisallowedDecisionsListBuilder",
 *     "form" = {
 *       "add" = "Drupal\paatokset_ahjo_api\Form\DisallowedDecisionsForm",
 *       "edit" = "Drupal\paatokset_ahjo_api\Form\DisallowedDecisionsForm",
 *       "delete" = "Drupal\paatokset_ahjo_api\Form\DisallowedDecisionsDeleteForm"
 *     },
 *     "route_provider" = {
 *       "html" = "Drupal\Core\Entity\Routing\AdminHtmlRouteProvider",
 *     },
 *   },
 *   config_prefix = "disallowed_decisions",
 *   config_export = {
 *     "id",
 *     "label",
 *     "configuration",
 *   },
 *   admin_permission = "administer paatokset",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label",
 *     "uuid" = "uuid",
 *     "configuration" = "configuration",
 *   },
 *   links = {
 *     "add-form" = "/admin/config/system/disallowed-decisions/add",
 *     "edit-form" = "/admin/config/system/disallowed-decisions/{disallowed_decisions}/edit",
 *     "delete-form" = "/admin/config/system/disallowed-decisions/{disallowed_decisions}/delete",
 *     "collection" = "/admin/config/system/disallowed-decisions"
 *   }
 * )
 */
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
