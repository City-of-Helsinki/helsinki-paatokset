<?php

declare(strict_types=1);

namespace Drupal\paatokset_allu\Entity;

/**
 * Defines the decision entity class.
 *
 * @ContentEntityType(
 *   id = "paatokset_allu_approval",
 *   label = @Translation("Approval"),
 *   label_collection = @Translation("Approvals"),
 *   label_singular = @Translation("approval"),
 *   label_plural = @Translation("approvals"),
 *   label_count = @PluralTranslation(
 *     singular = "@count approval",
 *     plural = "@count approvals",
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
 *   base_table = "paatokset_allu_approval",
 *   admin_permission = "administer paatokset_allu_document types",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label",
 *     "uuid" = "uuid",
 *   },
 *   links = {
 *     "collection" = "/admin/content/allu/approval",
 *     "canonical" = "/allu/document/approval/{paatokset_allu_decision}",
 *     "delete-form" = "/allu/document/approval/{paatokset_allu_decision}/delete",
 *     "delete-multiple-form" = "/admin/content/allu/approval/delete-multiple",
 *   },
 * )
 */
class Approval extends Document {

  /**
   * {@inheritdoc}
   */
  public const DOCUMENT_ROUTE = 'paatokset_allu.approval';

}
