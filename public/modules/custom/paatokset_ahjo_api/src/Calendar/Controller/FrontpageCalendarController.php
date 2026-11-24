<?php

declare(strict_types=1);

namespace Drupal\paatokset_ahjo_api\Calendar\Controller;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\paatokset_ahjo_api\Calendar\UpcomingMeetings;
use Drupal\paatokset_ahjo_api\Entity\Meeting;
use Drupal\paatokset_ahjo_api\Entity\Policymaker;

/**
 * Renders the frontpage calendar.
 *
 * @see \Drupal\paatokset_ahjo_api\Plugin\Block\FrontpageCalendarBlock
 */
final class FrontpageCalendarController extends ControllerBase {

  /**
   * Maximum number of meetings to show.
   */
  private const int LIMIT = 8;

  public function __construct(
    private readonly UpcomingMeetings $upcomingMeetings,
    private readonly EntityRepositoryInterface $entityRepository,
  ) {
  }

  /**
   * Builds the frontpage calendar.
   *
   * @return array<string, mixed>
   *   The render array.
   */
  public function content(): array {
    $config = $this->config('paatokset_ahjo_api.default_texts');

    $build = [];
    $cache = CacheableMetadata::createFromObject($config)
      ->addCacheTags(['node_list:meeting'])
      ->addCacheContexts(['languages:language_content'])
      ->setCacheMaxAge($this->upcomingMeetings->getCacheMaxAge());

    $meetings = $this->upcomingMeetings->load(excludeCancelled: TRUE, limit: self::LIMIT);
    $policymakers = $this->loadPolicymakers(array_map(
      static fn (Meeting $meeting) => $meeting->getPolicymakerId(),
      $meetings,
    ));

    $rows = [];
    foreach ($meetings as $meeting) {
      $policymaker = $policymakers[$meeting->getPolicymakerId()] ?? NULL;

      $cache->addCacheableDependency($meeting);
      if ($policymaker) {
        $cache->addCacheableDependency($policymaker);
      }

      $rows[] = [
        'meeting' => $meeting,
        'policymaker' => $policymaker,
      ];
    }

    $cache->applyTo($build);

    if (!$rows) {
      return $build;
    }

    return $build + [
      '#theme' => 'frontpage_calendar',
      '#meetings' => $rows,
      '#calendar_notice' => $config->get('calendar_notice_text'),
      '#calendar_link' => match ($this->languageManager()->getCurrentLanguage(LanguageInterface::TYPE_URL)->getId()) {
        'fi' => '/fi/kokouskalenteri',
        'sv' => '/sv/moteskalender',
        'en' => '/en/meetings-calendar',
        default => NULL,
      },
    ];
  }

  /**
   * Loads policymakers with a single query.
   *
   * @param array<string|null> $ids
   *   Policymaker IDs.
   *
   * @return array<string, \Drupal\paatokset_ahjo_api\Entity\Policymaker>
   *   Policymakers in the current language, keyed by policymaker ID.
   */
  private function loadPolicymakers(array $ids): array {
    $ids = array_filter($ids);
    if (!$ids) {
      return [];
    }

    $storage = $this->entityTypeManager()->getStorage('node');
    $nids = $storage->getQuery()
      ->accessCheck()
      ->condition('type', 'policymaker')
      ->condition('status', 1)
      ->condition('field_policymaker_id', $ids, 'IN')
      ->execute();

    $result = [];
    foreach ($storage->loadMultiple($nids) as $policymaker) {
      assert($policymaker instanceof Policymaker);
      $policymaker = $this->entityRepository->getTranslationFromContext($policymaker);
      $result[$policymaker->getPolicymakerId()] = $policymaker;
    }

    return $result;
  }

}
