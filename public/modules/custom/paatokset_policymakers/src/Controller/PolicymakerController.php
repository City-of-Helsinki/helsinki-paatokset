<?php

namespace Drupal\paatokset_policymakers\Controller;

use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\node\NodeInterface;
use Drupal\paatokset_policymakers\Service\OrganizationPathBuilder;
use Drupal\paatokset_policymakers\Service\PolicymakerService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Controller class for policymaker custom routes.
 */
class PolicymakerController extends ControllerBase {

  use StringTranslationTrait;

  /**
   * Controller for policymaker subpages.
   *
   * @param \Drupal\Core\Config\ImmutableConfig $config
   *   The config.
   * @param \Drupal\paatokset_policymakers\Service\PolicymakerService $policymakerService
   *   Policymaker service.
   * @param \Drupal\paatokset_policymakers\Service\OrganizationPathBuilder $organizationPathBuilderService
   *   Organization path builder service.
   */
  public function __construct(
    private ImmutableConfig $config,
    private PolicymakerService $policymakerService,
    private OrganizationPathBuilder $organizationPathBuilderService,
  ) {
    $this->policymakerService->setPolicyMakerByPath();
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('config.factory')->get('paatokset_ahjo_api.default_texts'),
      $container->get('paatokset_policymakers'),
      $container->get(OrganizationPathBuilder::class)
    );
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

    $organizationPath = $this->organizationPathBuilderService->build($policymaker);
    $organizationTag = $this->policymakerService->getPolicymakerTag($policymaker);

    $documentsDescription = $policymaker->get('field_documents_description')->value;
    if (empty($documentsDescription)) {
      $documentsDescription = $this->config->get('documents_description.value');
    }

    $build = [
      '#type' => 'container',
      '#title' => $this->t('Decisions: @title', ['@title' => $this->policymakerService->getPolicymaker()->get('title')->value]),
      'content' => [
        'organization' => [
          '#prefix' => '<div class="policymaker-content policymaker-tags">',
          '#suffix' => '</div>',
          'tag' => $organizationTag,
          'path' => $organizationPath,
        ],
        'description' => [
          '#markup' => '<div class="policymaker-text">' . $documentsDescription . '</div>',
        ],
      ],
    ];

    return $build;
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

    return t('Documents: @name', ['@name' => $policymaker->get('title')->value]);
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

    $organizationPath = $this->organizationPathBuilderService->build($policymaker);
    $organizationTag = $this->policymakerService->getPolicymakerTag($policymaker);

    $decisionsDescription = $policymaker->get('field_decisions_description')->value;
    if (empty($decisionsDescription)) {
      $decisionsDescription = $this->config->get('decisions_description.value');
    }
    $build = [
      '#type' => 'container',
      '#title' => $this->t('Decisions: @title', ['@title' => $this->policymakerService->getPolicymaker()->get('title')->value]),
      'content' => [
        'organization' => [
          '#prefix' => '<div class="policymaker-content policymaker-tags">',
          '#suffix' => '</div>',
          'tag' => $organizationTag,
          'path' => $organizationPath,
        ],
        'description' => [
          '#markup' => '<div class="policymaker-text">' . $decisionsDescription . '</div>',
        ],
      ],
    ];

    return $build;
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

    return t('Decisions: @name', ['@name' => $policymaker->get('title')->value]);
  }

  /**
   * Policymaker dicussion minutes route.
   *
   * @return array
   *   Render array.
   */
  public function discussionMinutes(): array {
    $build = ['#title' => $this->t('Discussion minutes: @title', ['@title' => $this->policymakerService->getPolicymaker()->get('title')->value])];
    return $build;
  }

  /**
   * Return view for singular minutes.
   *
   * @param string $id
   *   Meeting ID.
   *
   * @return array
   *   Render array.
   */
  public function minutes(string $id): array {
    $meetingData = $this->policymakerService->getMeetingAgenda($id);

    $build = [
      '#theme' => 'policymaker_minutes',
    ];

    if ($meetingData) {
      $policymaker = $this->policymakerService->getPolicymaker();
      $documentsDescription = $policymaker->get('field_documents_description')->value;
      if (empty($documentsDescription)) {
        $documentsDescription = $this->config->get('documents_description.value');
      }

      $build['meeting'] = $meetingData['meeting'];
      $build['list'] = $meetingData['list'];
      $build['file'] = $meetingData['file'];
      $build['#documents_description'] = '<div>' . $documentsDescription . '</div>';

      // Add cache context for current node.
      $build['#cache']['tags'][] = 'node:' . $meetingData['meeting']['nid'];

      // Add cache context for meeting ID.
      $build['#cache']['tags'][] = 'meeting:' . $id;
    }

    // Add cache context for minutes of the discussion for the link to show up.
    $build['#cache']['tags'][] = 'media_list:minutes_of_the_discussion';

    if ($meetingData['decision_announcement']) {
      $build['decision_announcement'] = $meetingData['decision_announcement'];
    }

    if ($meetingData['meeting_metadata']) {
      $build['meeting_metadata'] = $meetingData['meeting_metadata'];
    }

    $minutesOfDiscussion = $this->policymakerService->getMinutesOfDiscussion(1, FALSE, $id);
    if ($minutesOfDiscussion) {
      $build['minutes_of_discussion'] = $minutesOfDiscussion;
    }

    return $build;
  }

  /**
   * Return title as translatable string.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   *   Discussions title.
   */
  public static function getDiscussionMinutesTitle(): TranslatableMarkup {
    $policymakerService = \Drupal::service('paatokset_policymakers');
    $policymakerService->setPolicyMakerByPath();
    $policymaker = $policymakerService->getPolicymaker();

    if (!$policymaker instanceof NodeInterface) {
      return t('Discussion minutes');
    }

    return t('Discussion minutes: @name', ['@name' => $policymaker->get('title')->value]);
  }

  /**
   * Return translatable title for minutes.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup|string
   *   Minutes title.
   */
  public function getMinutesTitle($id) {
    $meeting = $this->policymakerService->getMeetingNode($id);
    if ($meeting instanceof NodeInterface) {
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

}
