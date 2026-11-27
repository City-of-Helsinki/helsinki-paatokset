<?php

declare(strict_types=1);

namespace Drupal\paatokset_ahjo_api\Calendar\Controller;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\DependencyInjection\AutowireTrait;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\paatokset_ahjo_api\Calendar\UpcomingMeetings;
use Drupal\paatokset_ahjo_api\Entity\Meeting;
use Drupal\paatokset_ahjo_api\Entity\Policymaker;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Renders the policymaker calendar.
 *
 * @see \Drupal\paatokset_ahjo_api\Plugin\Block\PolicymakerCalendarBlock
 */
final class PolicymakerCalendarController implements ContainerInjectionInterface {

  use AutowireTrait;

  public function __construct(
    private readonly UpcomingMeetings $upcomingMeetings,
    private readonly EntityRepositoryInterface $entityRepository,
    private readonly ConfigFactoryInterface $configFactory,
  ) {
  }

  /**
   * Builds the policymaker calendar.
   *
   * @param \Drupal\paatokset_ahjo_api\Entity\Policymaker $node
   *   The policymaker.
   *
   * @return array<string, mixed>
   *   The render array.
   */
  public function content(Policymaker $node): array {
    $policymakerId = $node->getPolicymakerId();
    if (!$policymakerId) {
      throw new NotFoundHttpException();
    }

    $policymaker = $this->entityRepository->getTranslationFromContext($node);
    assert($policymaker instanceof Policymaker);

    $config = $this->configFactory->get('paatokset_ahjo_api.default_texts');

    $build = [];
    $cache = CacheableMetadata::createFromObject($config)
      ->addCacheableDependency($policymaker)
      // Invalidated when meetings of this policymaker change.
      ->addCacheTags([Meeting::getPolicymakerCacheTag($policymakerId)])
      ->addCacheContexts(['languages:language_content'])
      ->setCacheMaxAge($this->upcomingMeetings->getCacheMaxAge());

    $meetings = $this->upcomingMeetings->load($policymakerId);
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

}
