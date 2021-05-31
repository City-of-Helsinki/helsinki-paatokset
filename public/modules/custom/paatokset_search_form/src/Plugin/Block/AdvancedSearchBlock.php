<?php

namespace Drupal\paatokset_search_form\Plugin\Block;

use Drupal\Core\Block\BlockBase;

/**
 * Provides a block containing search form.
 *
 * @Block(
 *  id="advanced_search_form_block",
 *  admin_label=@Translation("Advanced search form block")
 * )
 */
class AdvancedSearchBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    return \Drupal::formBuilder()->getForm('Drupal\paatokset_search_form\Form\AdvancedSearchForm');
  }

}
