<?php

namespace Drupal\paatokset_helsinki_kanava\Plugin\Block;

use Drupal\Core\Block\BlockBase;
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
  public function getCacheContexts() {
    return ['url.path', 'url.query_args'];
  }

  /**
   * Return true if it is less than one day to the next meeting.
   */
  private function shouldShowAlert($time) {
    // strtotime('+1 day') > strtotime($time);
    return TRUE;
  }

}
