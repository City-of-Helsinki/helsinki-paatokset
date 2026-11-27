<?php

namespace Drupal\paatokset\Plugin\search_api\tracker;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\search_api\Attribute\SearchApiTracker;
use Drupal\search_api\Tracker\TrackerPluginBase;

/**
 * The default tracked with improved indexing order.
 *
 * @todo Remove this once paatokset_search_update_11002() has been run on all
 *   environments. The decisions index uses the customized parallel tracker.
 */
#[SearchApiTracker(
  id: 'custom_basic_tracker',
  label: new TranslatableMarkup('Customized basic tracker'),
  description: new TranslatableMarkup('Based on the default tracker. Improved indexing order.')
)]
class CustomBasicTracker extends TrackerPluginBase {
}
