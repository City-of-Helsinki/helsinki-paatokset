<?php

declare(strict_types=1);

namespace Drupal\paatokset_allu\Entity;

/**
 * Defines the decision entity class.
 *
 * @ContentEntityType(
 *   id = "paatokset_allu_decision",
 *   label = @Translation("Decision"),
 *   label_collection = @Translation("Decisions"),
 *   label_singular = @Translation("decision"),
 *   label_plural = @Translation("decisions"),
 *   label_count = @PluralTranslation(
 *     singular = "@count decision",
 *     plural = "@count decisions",
 *   ),
 *   handlers = {
 *     "list_builder" = "Drupal\Core\Entity\EntityListBuilder",
 *     "view_builder" = "Drupal\Core\Entity\EntityViewBuilder",
 *     "views_data" = "Drupal\views\EntityViewsData",
 *     "access" = "Drupal\helfi_api_base\Entity\Access\RemoteEntityAccess",
 *     "form" = {
 *       "default" = "Drupal\Core\Entity\ContentEntityForm",
 *       "delete" = "Drupal\Core\Entity\ContentEntityDeleteForm",
 *     },
 *     "route_provider" = {
 *       "html" = "Drupal\helfi_api_base\Entity\Routing\EntityRouteProvider",
 *     },
 *   },
 *   base_table = "paatokset_allu_decision",
 *   admin_permission = "administer remote entities",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label",
 *     "uuid" = "uuid",
 *   },
 *   links = {
 *     "collection" = "/admin/content/allu/decision",
 *     "edit-form" = "/admin/content/allu/decision/{paatokset_allu_decision}/edit",
 *     "canonical" = "/allu/document/decision/{paatokset_allu_decision}",
 *     "delete-form" = "/allu/document/decision/{paatokset_allu_decision}/delete",
 *   },
 * )
 */
class Decision extends Document {

  /**
   * {@inheritdoc}
   */
  public const DOCUMENT_ROUTE = 'paatokset_allu.decision';

}
