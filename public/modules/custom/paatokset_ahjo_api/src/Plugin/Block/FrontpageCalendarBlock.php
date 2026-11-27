<?php

declare(strict_types=1);

namespace Drupal\paatokset_ahjo_api\Plugin\Block;

use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\paatokset_ahjo_api\Calendar\HtmxPlaceholder;

/**
 * Provides Calendar Block.
 *
 * The calendar content is loaded with htmx, so it can expire
 * when the day changes without affecting the page cache.
 *
 * @see \Drupal\paatokset_ahjo_api\Calendar\Controller\FrontpageCalendarController
 */
#[Block(
  id: 'frontpage_calendar',
  admin_label: new TranslatableMarkup('Paatokset frontpage calendar'),
  category: new TranslatableMarkup('Paatokset custom blocks'),
)]
final class FrontpageCalendarBlock extends BlockBase {

  /**
   * {@inheritDoc}
   */
  public function build(): array {
    return HtmxPlaceholder::build(Url::fromRoute('paatokset_ahjo_api.frontpage_calendar'));
  }

}
