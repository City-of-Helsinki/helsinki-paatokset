<?php

declare(strict_types=1);

namespace Drupal\paatokset\Hook;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;

/**
 * Alters the Date range field to allow years past 2050.
 *
 * @todo This can be removed once https://www.drupal.org/project/drupal/issues/2836054
 * is fixed.
 */
final class DateRangeHook {

  /**
   * Implements 'hook_form_FORM_ID_alter().
   */
  #[Hook(hook: 'form_node_form_alter')]
  public function alterDateRange(array &$form, FormStateInterface $formState): void {
    if (!isset($form['field_policymaker_dissolved'])) {
      return;
    }
    $form['field_policymaker_dissolved']['widget'][0]['value']['#date_year_range'] = '1900:2101';
  }

}
