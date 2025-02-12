<?php

declare(strict_types=1);

namespace Drupal\paatokset_allu\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\DependencyInjection\AutowireTrait;
use Drupal\paatokset_allu\ApprovalType;
use Drupal\paatokset_allu\Client\Client;
use Drupal\paatokset_allu\Entity\Document;
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
   * @param \Drupal\paatokset_allu\Entity\Document $paatokset_allu_document
   *   Decision id.
   *
   * @return \Symfony\Component\HttpFoundation\StreamedResponse
   *   PDF document.
   */
  public function decision(Document $paatokset_allu_document): StreamedResponse {
    return $this->client->streamDecision($paatokset_allu_document->id());
  }

  /**
   * Stream approval.
   *
   * @param \Drupal\paatokset_allu\Entity\Document $paatokset_allu_document
   *   Decision id.
   * @param string $type
   *   Approval type.
   *
   * @return \Symfony\Component\HttpFoundation\StreamedResponse
   *   PDF document.
   */
  public function approval(Document $paatokset_allu_document, string $type): StreamedResponse {
    $approvalType = ApprovalType::tryFrom($type);
    if (!$approvalType) {
      throw new NotFoundHttpException();
    }

    return $this->client->streamApproval($paatokset_allu_document->id(), $approvalType);
  }

}
