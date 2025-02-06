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
 *     "list_builder" = "Drupal\paatokset_allu\Entity\Listing\ListBuilder",
 *     "views_data" = "Drupal\views\EntityViewsData",
 *     "form" = {
 *       "default" = "Drupal\Core\Entity\ContentEntityForm",
 *       "delete" = "Drupal\Core\Entity\ContentEntityDeleteForm",
 *       "delete-multiple-confirm" = "Drupal\Core\Entity\Form\DeleteMultipleForm",
 *     },
 *     "route_provider" = {
 *       "html" = "Drupal\paatokset_allu\Routing\DocumentHtmlRouteProvider",
 *     },
 *   },
 *   base_table = "paatokset_allu_decision",
 *   admin_permission = "administer paatokset_allu_document types",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label",
 *     "uuid" = "uuid",
 *   },
 *   links = {
 *     "collection" = "/admin/content/allu/decision",
 *     "canonical" = "/allu/document/decision/{paatokset_allu_decision}",
 *     "delete-form" = "/allu/document/decision/{paatokset_allu_decision}/delete",
 *     "delete-multiple-form" = "/admin/content/allu/decision/delete-multiple",
 *   },
 * )
 */
class Decision extends Document {

  /**
   * {@inheritdoc}
   */
  public const DOCUMENT_ROUTE = 'paatokset_allu.decision';

}
