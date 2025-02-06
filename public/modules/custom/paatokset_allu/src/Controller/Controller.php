<?php

declare(strict_types=1);

namespace Drupal\paatokset_allu\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\DependencyInjection\AutowireTrait;
use Drupal\paatokset_allu\ApprovalType;
use Drupal\paatokset_allu\Client\Client;
use Drupal\paatokset_allu\Entity\Approval;
use Drupal\paatokset_allu\Entity\Decision;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Allu controller.
 */
class Controller extends ControllerBase {

  use AutowireTrait;

  /**
   * Constructs a new instance.
   *
   * @param \Drupal\paatokset_allu\Client\Client $client
   *   Allu API client.
   */
  public function __construct(private readonly Client $client) {
  }

  /**
   * Stream decision.
   *
   * @param \Drupal\paatokset_allu\Entity\Decision $document
   *   Decision id.
   *
   * @return \Symfony\Component\HttpFoundation\StreamedResponse
   *   PDF document.
   */
  public function decision(Decision $document): StreamedResponse {
    return $this->client->streamDecision($document->id());
  }

  /**
   * Stream approval.
   *
   * @param \Drupal\paatokset_allu\Entity\Approval $approval
   *   Decision id.
   * @param string $approvalType
   *   Approval type.
   *
   * @return \Symfony\Component\HttpFoundation\StreamedResponse
   *   PDF document.
   */
  public function approval(Approval $approval, string $approvalType): StreamedResponse {
    $approvalType = ApprovalType::tryFrom($approvalType);
    if (!$approvalType) {
      throw new NotFoundHttpException();
    }

    return $this->client->streamApproval($approval->id(), $approvalType);
  }

}
