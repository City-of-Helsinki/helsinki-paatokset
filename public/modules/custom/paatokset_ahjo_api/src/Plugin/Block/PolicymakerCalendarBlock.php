<?php

declare(strict_types=1);

namespace Drupal\paatokset_ahjo_api\Plugin\Block;

use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;
use Drupal\paatokset_ahjo_api\Entity\Meeting;
use Drupal\paatokset_ahjo_api\Entity\Policymaker;

/**
 * Provides policymaker calendar block.
 */
#[Block(
  id: 'policymaker_calendar',
  admin_label: new TranslatableMarkup('Paatokset policymaker calendar'),
  category: new TranslatableMarkup('Paatokset custom blocks'),
)]
final class PolicymakerCalendarBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * Constructs a new instance.
   *
   * @phpstan-param array<string, mixed> $configuration
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected readonly RouteMatchInterface $routeMatch,
    protected readonly ConfigFactoryInterface $configFactory,
    protected readonly EntityTypeManagerInterface $entityTypeManager,
    protected readonly EntityRepositoryInterface $entityRepository,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritDoc}
   *
   * @phpstan-return array<string, mixed>
   */
  public function build(): array {
    $build = [];

    $config = $this->configFactory->get('paatokset_ahjo_api.default_texts');
    $cache = CacheableMetadata::createFromObject($config)
      ->addCacheContexts(['route']);

    // This block is only shown on policymaker node pages.
    $policymaker = $this->routeMatch->getParameter('node');

    if (!$policymaker instanceof Policymaker) {
      $cache->applyTo($build);
      return $build;
    }

    $cache->addCacheableDependency($policymaker);

    // Invalidated when meetings of this policymaker change.
    if ($policymakerId = $policymaker->getPolicymakerId()) {
      $cache->addCacheTags([Meeting::getPolicymakerCacheTag($policymakerId)]);
    }

    $meetings = $this->loadMeetings($policymaker);
    foreach ($meetings as $meeting) {
      $cache->addCacheableDependency($meeting);
    }

    $cache->applyTo($build);

    if (!$meetings) {
      return $build;
    }

    $description = ($value = $policymaker->getMeetingsDescription())
      ? ['value' => $value]
      : $config->get('meetings_description');

    return $build + [
      '#theme' => 'policymaker_calendar',
      '#meetings' => $meetings,
      '#policymaker' => $policymaker,
      '#description' => _paatokset_ahjo_api_render_default_text($description),
    ];
  }

  /**
   * Loads upcoming meetings of the given policymaker.
   *
   * @return \Drupal\paatokset_ahjo_api\Entity\Meeting[]
   *   Meetings in the current language, ordered by meeting date.
   */
  private function loadMeetings(Policymaker $policymaker): array {
    // @fixme this does not re-run the query when date changes.
    $today = (new \DateTimeImmutable('today', new \DateTimeZone(DateTimeItemInterface::STORAGE_TIMEZONE)))
      ->format(DateTimeItemInterface::DATETIME_STORAGE_FORMAT);

    $storage = $this->entityTypeManager->getStorage('node');
    $nids = $storage->getQuery()
      ->accessCheck()
      ->condition('type', 'meeting')
      ->condition('status', 1)
      ->condition('field_meeting_dm_id', $policymaker->getPolicymakerId())
      ->condition('field_meeting_date', $today, '>=')
      ->sort('field_meeting_date')
      ->execute();

    $result = [];
    foreach ($storage->loadMultiple($nids) as $meeting) {
      assert($meeting instanceof Meeting);
      $result[] = $this->entityRepository->getTranslationFromContext($meeting);
    }

    return $result;
  }

}
