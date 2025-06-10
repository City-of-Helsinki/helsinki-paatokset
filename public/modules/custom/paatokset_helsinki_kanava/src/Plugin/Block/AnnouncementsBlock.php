<?php

namespace Drupal\paatokset_helsinki_kanava\Plugin\Block;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Link;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\paatokset_ahjo_api\Service\MeetingService;
use Drupal\paatokset_policymakers\Service\PolicymakerService;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides Agendas Submenu Block.
 */
#[Block(
  id: "paatokset_helsinki_kanava_announcements",
  admin_label: new TranslatableMarkup("Helsinki Kanava announcements"),
)]
final class AnnouncementsBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * Number of seconds before the meeting when alert becomes visible.
   */
  public const ALERT_OFFSET = 60 * 60 * 24;

  /**
   * Minimum time the block can cache. Set to 10 minutes.
   */
  public const MIN_CACHE_TTL = 60 * 10;

  /**
   * {@inheritDoc}
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    private readonly ConfigFactoryInterface $config,
    private readonly PolicymakerService $policymakerService,
    private readonly MeetingService $meetingService,
    private readonly DateFormatterInterface $formatter,
    private readonly TimeInterface $time,
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
      $container->get('paatokset_ahjo_meetings'),
      $container->get(DateFormatterInterface::class),
      $container->get(TimeInterface::class)
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    $council_id = $this->config->get('paatokset_helsinki_kanava.settings')->get('city_council_id');
    $councilNode = $this->policymakerService->getPolicyMaker($council_id);
    $ttl = Cache::PERMANENT;

    // @todo This should be based on video entities like hook_preprocess_node__policymaker, UHF-9549.
    $announcement = [];
    if ($councilNode) {
      $nextMeetingTimestamp = (int) $this->meetingService->nextMeetingDate($council_id);
      $timeToNextMeeting = max($nextMeetingTimestamp - $this->time->getCurrentTime(), 0);

      // Cache until the alert should be shown.
      $ttl = max($timeToNextMeeting - self::ALERT_OFFSET, self::MIN_CACHE_TTL);

      if ($nextMeetingTimestamp && $this->shouldShowAlert($nextMeetingTimestamp)) {
        // If the alert is shown, cache until meeting starts.
        $ttl = max($timeToNextMeeting, self::MIN_CACHE_TTL);

        $announcement['text'] = $this->t('Next council stream will start on @date at @time',
          [
            '@date' => $this->formatter->format($nextMeetingTimestamp, 'custom', 'd.m', 'Europe/Helsinki'),
            '@time' => $this->formatter->format($nextMeetingTimestamp, 'custom', 'H:i', 'Europe/Helsinki'),
          ]
        );

        $url = $councilNode->toUrl(options: [
          'fragment' => 'policymaker-live-stream',
        ]);

        if ($url) {
          $linkText = $this->t("You can see the stream on council's page, starting at @time", [
            '@time' => $this->formatter->format($nextMeetingTimestamp, 'custom', 'H:i', 'Europe/Helsinki'),
          ]);
          $announcement['link'] = Link::fromTextAndUrl($linkText, $url);
        }
      }
    }

    return [
      '#cache' => [
        'max-age' => $ttl,
        'contexts' => ['url.path'],
      ],
      'announcement' => $announcement,
    ];
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
   * @param int $timestamp
   *   Next meeting timestamp.
   *
   * @return bool
   *   If alert should be displayed.
   */
  private function shouldShowAlert(int $timestamp): bool {
    if ($this->config->get('paatokset_helsinki_kanava.settings')->get('debug_mode')) {
      return TRUE;
    }
    return $this->time->getCurrentTime() + self::ALERT_OFFSET > $timestamp;
  }

}
