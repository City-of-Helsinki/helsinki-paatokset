<?php

namespace Drupal\paatokset_helsinki_kanava\Plugin\Block;

use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Link;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\paatokset_ahjo_api\Service\MeetingService;
use Drupal\paatokset_policymakers\Service\PolicymakerService;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides Agendas Submenu Block.
 */
#[Block(
  id: "paatokset_helsinki_kanava_announcements",
  admin_label: new TranslatableMarkup("Helsinki Kanava announcements"),
  category: new TranslatableMarkup("Paatokset custom blocks")
)]
final class AnnouncementsBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * {@inheritDoc}
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    private ConfigFactoryInterface $config,
    private PolicymakerService $policymakerService,
    private MeetingService $meetingService,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new self(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('config.factory'),
      $container->get('paatokset_policymakers'),
      $container->get('paatokset_ahjo_meetings')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    $council_id = $this->config->get('paatokset_helsinki_kanava.settings')->get('city_council_id');
    $councilNode = $this->policymakerService->getPolicyMaker($council_id);

    $announcement = [];
    if ($councilNode) {
      $nextMeetingDate = $this->meetingService->nextMeetingDate($council_id);

      if ($nextMeetingDate && $this->shouldShowAlert($nextMeetingDate)) {

        $announcement['text'] = $this->t('Next council stream will start on @date at @time',
          [
            '@date' => date('d.m', $nextMeetingDate),
            '@time' => date('H:i', $nextMeetingDate),
          ]
        );

        $url = $councilNode->toUrl();
        if ($url) {
          $linkText = $this->t("You can see the stream on council's page, starting at @time", [
            '@time' => date('H:i', $nextMeetingDate),
          ]);
          $announcement['link'] = Link::fromTextAndUrl($linkText, Url::fromUri('internal:' . $url->toString() . '#policymaker-live-stream'));
        }
      }
    }

    return [
      '#cache' => ['contexts' => ['url.path']],
      'announcement' => $announcement,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts(): array {
    return ['url.path', 'url.query_args'];
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags(): array {
    return ['meeting_video_list', 'node_list:meeting'];
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
    if ($this->config->get('paatokset_helsinki_kanava.settings')->get('debug_mode')) {
      return TRUE;
    }
    return strtotime('+1 day') > (int) $time;
  }

}
