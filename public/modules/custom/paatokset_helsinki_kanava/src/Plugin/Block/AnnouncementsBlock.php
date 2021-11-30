<?php

namespace Drupal\paatokset_helsinki_kanava\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\node\Entity\Node;

/**
 * Provides Agendas Submenu Block.
 *
 * @Block(
 *    id = "paatokset_helsinki_kanava_announcements",
 *    admin_label = @Translation("Helsinki Kanava announcements"),
 *    category = @Translation("Paatokset custom blocks")
 * )
 */
class AnnouncementsBlock extends BlockBase {

  /**
   * Build the attributes.
   */
  public function build() {
    $council = \Drupal::config('paatokset_helsinki_kanava.settings')->get('city_council_node');
    $councilNode = Node::load($council);
    $announcement = [];
    if ($councilNode) {
      $meetingsService = \Drupal::service('Drupal\paatokset_ahjo_api\Service\MeetingService');
      $nextMeetingDate = $meetingsService->nextMeetingDate($councilNode->get('title')->value);

      if ($nextMeetingDate && $this->shouldShowAlert($nextMeetingDate)) {

        $announcement['text'] = t('Next meeting will be held on @date at @time',
          [
            '@date' => date('d.m', $nextMeetingDate),
            '@time' => date('H:i', $nextMeetingDate),
          ]
        );
      }

      $url = $councilNode->toUrl();
      if ($url) {
        $linkText = t("You can see the stream on council's page");
        $announcement['link'] = Link::fromTextAndUrl($linkText, Url::fromUri('internal:' . $url->toString() . '#policymaker-live-stream'));
      }
    }

    return [
      '#cache' => ['contexts' => ['url.path']],
      'announcement' => $announcement,
    ];
  }

  /**
   * Set cache age to zero.
   */
  public function getCacheMaxAge() {
    // If you need to redefine the Max Age for that block.
    return 0;
  }

  /**
   * Get cache contexts.
   */
  public function getCacheContexts(): array {
    return ['url.path', 'url.query_args'];
  }

  /**
   * Check if it is less than one day to the next meeting or test mode is on.
   *
   * @param string $time
   *   Time of next meeting.
   *
   * @return bool
   *   If alert should be displayed.
   */
  private function shouldShowAlert(string $time): bool {
    if (\Drupal::config('paatokset_helsinki_kanava.settings')->get('debug_mode')) {
      return TRUE;
    }

    return strtotime('+1 day') > $time;
  }

}
