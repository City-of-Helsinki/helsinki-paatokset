<?php

declare(strict_types=1);

namespace Drupal\paatokset_ahjo_api\Plugin\Block;

use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;
use Drupal\paatokset_ahjo_api\Entity\Meeting;
use Drupal\paatokset_ahjo_api\Entity\Policymaker;

/**
 * Provides Calendar Block.
 */
#[Block(
  id: 'frontpage_calendar',
  admin_label: new TranslatableMarkup('Paatokset frontpage calendar'),
  category: new TranslatableMarkup('Paatokset custom blocks'),
)]
final class FrontpageCalendarBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * Maximum number of meetings to show.
   */
  private const int LIMIT = 8;

  /**
   * Constructs a new instance.
   *
   * @phpstan-param array<string, mixed> $configuration
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected readonly ConfigFactoryInterface $configFactory,
    protected readonly LanguageManagerInterface $languageManager,
    protected readonly EntityTypeManagerInterface $entityTypeManager,
    protected readonly EntityRepositoryInterface $entityRepository,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritDoc}
   */
  public function build(): array {
    $config = $this->configFactory->get('paatokset_ahjo_api.default_texts');

    $build = [];
    $cache = CacheableMetadata::createFromObject($config)
      ->addCacheTags(['node_list:meeting']);

    $meetings = $this->loadMeetings();
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

    $build += [
      '#theme' => 'frontpage_calendar',
      '#meetings' => $rows,
      '#calendar_notice' => $config->get('calendar_notice_text'),
      '#calendar_link' => match ($this->languageManager->getCurrentLanguage()->getId()) {
        'fi' => '/fi/kokouskalenteri',
        'sv' => '/sv/moteskalender',
        'en' => '/en/meetings-calendar',
        default => NULL,
      },
    ];

    return $build;
  }

  /**
   * Loads upcoming, non-cancelled meetings.
   *
   * @return \Drupal\paatokset_ahjo_api\Entity\Meeting[]
   *   Meetings in the current language, ordered by meeting date.
   */
  private function loadMeetings(): array {
    // @fixme this does not re-run the query when date changes.
    $today = (new \DateTimeImmutable('today', new \DateTimeZone(DateTimeItemInterface::STORAGE_TIMEZONE)))
      ->format(DateTimeItemInterface::DATETIME_STORAGE_FORMAT);

    $storage = $this->entityTypeManager->getStorage('node');
    $nids = $storage->getQuery()
      ->accessCheck()
      ->condition('type', 'meeting')
      ->condition('status', 1)
      ->condition('field_meeting_date', $today, '>=')
      ->condition('field_meeting_status', 'peruttu', '<>')
      ->sort('field_meeting_date')
      ->range(0, self::LIMIT)
      ->execute();

    $result = [];
    foreach ($storage->loadMultiple($nids) as $meeting) {
      assert($meeting instanceof Meeting);
      $result[] = $this->entityRepository->getTranslationFromContext($meeting);
    }

    return $result;
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

    $storage = $this->entityTypeManager->getStorage('node');
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
