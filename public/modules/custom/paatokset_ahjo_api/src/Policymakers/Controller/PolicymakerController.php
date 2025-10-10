<?php

declare(strict_types=1);

namespace Drupal\paatokset_ahjo_api\Policymakers\Controller;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\DependencyInjection\AutowireTrait;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\node\NodeInterface;
use Drupal\paatokset_ahjo_api\Entity\Meeting;
use Drupal\paatokset_ahjo_api\Entity\Policymaker;
use Drupal\paatokset_ahjo_api\Service\OrganizationPathBuilder;
use Drupal\paatokset_policymakers\Service\PolicymakerService;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Controller class for policymaker custom routes.
 */
class PolicymakerController extends ControllerBase {

  use StringTranslationTrait;
  use AutowireTrait;

  public function __construct(
    #[Autowire(service: 'paatokset_policymakers')]
    private readonly PolicymakerService $policymakerService,
    private readonly OrganizationPathBuilder $organizationPathBuilderService,
  ) {
    // Set magic values in policymaker service.
    $this->policymakerService->setPolicyMakerByPath();
  }

  /**
   * Build documents and decisions list.
   */
  private function buildDocumentPage(Policymaker $policymaker, string $description): array {
    $organizationPath = $this->organizationPathBuilderService->build($policymaker);
    $organizationTag = $this->policymakerService->getPolicymakerTag($policymaker);

    return [
      '#type' => 'container',
      '#title' => $this->t('Decisions: @title', ['@title' => $policymaker->label()]),
      'content' => [
        'organization' => [
          '#prefix' => '<div class="policymaker-content policymaker-tags">',
          '#suffix' => '</div>',
          'tag' => $organizationTag,
          'path' => $organizationPath,
        ],
        'description' => [
          '#markup' => '<div class="policymaker-text">' . $description . '</div>',
        ],
      ],
    ];
  }

  /**
   * Policymaker documents route.
   *
   * @return array
   *   Render array.
   */
  public function documents(): array {
    $policymaker = $this->policymakerService->getPolicymaker();
    if (!$policymaker instanceof NodeInterface) {
      return [];
    }

    $description = $policymaker->get('field_documents_description')->value
      ?: $this->getDefaultText('documents_description.value')
      ?: '';

    return $this->buildDocumentPage($policymaker, $description);
  }

  /**
   * Policymaker decisions route.
   *
   * @return array
   *   Render array.
   */
  public function decisions(): array {
    $policymaker = $this->policymakerService->getPolicymaker();
    if (!$policymaker instanceof NodeInterface) {
      return [];
    }

    $description = $policymaker->get('field_decisions_description')->value
      ?: $this->getDefaultText('decisions_description.value')
      ?: '';

    return $this->buildDocumentPage($policymaker, $description);
  }

  /**
   * Return title as translatable string.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   *   Documents title.
   */
  public static function getDocumentsTitle(): TranslatableMarkup {
    $policymakerService = \Drupal::service('paatokset_policymakers');
    $policymakerService->setPolicyMakerByPath();
    $policymaker = $policymakerService->getPolicymaker();

    if (!$policymaker instanceof NodeInterface) {
      return t('Documents');
    }

    return t('Documents: @name', ['@name' => $policymaker->label()]);
  }

  /**
   * Return title as translatable string.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   *   Decisions title.
   */
  public static function getDecisionsTitle(): TranslatableMarkup {
    $policymakerService = \Drupal::service('paatokset_policymakers');
    $policymakerService->setPolicyMakerByPath();
    $policymaker = $policymakerService->getPolicymaker();

    if (!$policymaker instanceof NodeInterface) {
      return t('Decisions');
    }

    return t('Decisions: @name', ['@name' => $policymaker->label()]);
  }

  /**
   * Policymaker discussion minutes route.
   *
   * @return array
   *   Render array.
   */
  public function discussionMinutes(): array {
    $policymaker = $this->policymakerService->getPolicymaker();
    return [
      '#title' => $this->t('Discussion minutes: @title', [
        '@title' => $policymaker?->label() ?? '',
      ]),
    ];
  }

  /**
   * Return view for singular minutes.
   *
   * @todo get meeting & policymaker from route paramters.
   *
   * @param string $id
   *   Meeting ID.
   *
   * @return array
   *   Render array.
   */
  public function minutes(string $id): array {
    // Load meeting:
    $meetings = $this->entityTypeManager()
      ->getStorage('node')
      ->loadByProperties([
        'status' => 1,
        'type' => 'meeting',
        'field_meeting_id' => $id,
      ]);

    if (!$meeting = array_first($meetings)) {
      throw new NotFoundHttpException();
    }

    assert($meeting instanceof Meeting);
    if (!$policymaker = $meeting->getPolicymaker()) {
      throw new NotFoundHttpException();
    }

    $cache = new CacheableMetadata();
    $build = [
      '#theme' => 'policymaker_minutes',
    ];

    $meetingData = $this->policymakerService->getMeetingAgenda($meeting);
    if ($meetingData) {
      $documentsDescription = _paatokset_ahjo_api_render_default_text(['value' => $policymaker->get('field_documents_description')->value]);
      if (empty($documentsDescription)) {
        // I don't think $documentsDescription can ever be empty.
        $documentsDescription = _paatokset_ahjo_api_render_default_text($this->getDefaultText('documents_description'));
      }

      $build['meeting'] = $meetingData['meeting'];
      $build['list'] = $meetingData['list'];
      $build['file'] = $meetingData['file'];
      $build['#documents_description'] = $documentsDescription;

      // Add cache context for the current node.
      $cache->addCacheableDependency($meeting);

      // Add cache context for meeting ID.
      $cache->addCacheTags(["meeting:$id"]);

      if (isset($meetingData['decision_announcement'])) {
        $build['decision_announcement'] = $meetingData['decision_announcement'];
      }

      if (isset($meetingData['meeting_metadata'])) {
        $build['meeting_metadata'] = $meetingData['meeting_metadata'];
      }
    }

    // Add cache context for minutes of the discussion for the link to show up.
    $cache->addCacheTags(["media_list:minutes_of_the_discussion"]);
    if ($minutesOfDiscussion = $this->getMinutesOfDiscussion($meeting)) {
      $build['minutes_of_discussion'] = $minutesOfDiscussion;
    }

    $cache->applyTo($build);

    return $build;
  }

  /**
   * Get discussion minutes for meeting.
   *
   * @return array
   *   Meeting document data.
   */
  private function getMinutesOfDiscussion(Meeting $meeting) : array {
    $mediaStorage = $this
      ->entityTypeManager()
      ->getStorage('media');

    $meeting_minutes = $mediaStorage->loadByProperties([
      'bundle' => 'minutes_of_the_discussion',
      'field_meetings_reference' => $meeting->id(),
    ]);

    $transformedResults = [];
    foreach ($meeting_minutes as $entity) {
      /** @var \Drupal\file\Entity\File|null $file */
      $file = $entity->get('field_document')->entity;
      $download_link = $file?->createFileUrl(relative: FALSE);
      if (!$download_link) {
        continue;
      }

      $meeting_timestamp = $meeting->get('field_meeting_date')->date->getTimeStamp();

      $transformedResults[] = [
        'link' => $download_link,
        'publish_date' => date('d.m.Y', $meeting_timestamp),
        'title' => $entity->label() . ' (PDF)',
        'type' => 'minutes-of-discussion',
        'year' => date('Y', $meeting_timestamp),
      ];
    }

    usort($transformedResults, function ($item1, $item2) {
      return strtotime($item2['publish_date']) - strtotime($item1['publish_date']);
    });

    return $transformedResults;
  }

  /**
   * Return title as translatable string.
   */
  public static function getDiscussionMinutesTitle(): TranslatableMarkup {
    $policymakerService = \Drupal::service('paatokset_policymakers');
    $policymakerService->setPolicyMakerByPath();
    $policymaker = $policymakerService->getPolicymaker();

    if (!$policymaker instanceof NodeInterface) {
      return t('Discussion minutes');
    }

    return t('Discussion minutes: @name', ['@name' => $policymaker->label()]);
  }

  /**
   * Return translatable title for minutes.
   */
  public function getMinutesTitle($id): TranslatableMarkup|string {
    // Load meeting:
    $meetings = $this->entityTypeManager()
      ->getStorage('node')
      ->loadByProperties([
        'status' => 1,
        'type' => 'meeting',
        'field_meeting_id' => $id,
      ]);

    if ($meeting = array_first($meetings)) {
      return $this->policymakerService->getMeetingTitle($meeting);
    }

    return $this->t('Minutes');
  }

  /**
   * Get decision maker composition in JSON format.
   *
   * @param string $id
   *   Decision maker ID.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   Return response with JSON data.
   */
  public function orgComposition(string $id): JsonResponse {
    $this->policymakerService->setPolicyMaker($id);
    $data = $this->policymakerService->getComposition();
    return new JsonResponse($data);
  }

  /**
   * Get default texts from config.
   */
  private function getDefaultText(string $key): ?string {
    return $this->config('paatokset_ahjo_api.default_texts')->get($key);
  }

}
